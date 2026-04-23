<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\PasswordHistory;
use App\Models\PasswordPolicy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 3;

    /**
     * POST /api/auth/login
     * Returns: { user: UserResource, token: string }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Look up user regardless of active state (need to handle lock messages)
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        // Emergency admin: blocked when other active admins exist
        if ($user && $user->is_emergency_admin) {
            $otherActiveAdmins = User::where('is_emergency_admin', false)
                ->whereRaw("roles @> ?", [json_encode(['admin'])])
                ->where('is_active', true)
                ->exists();

            if ($otherActiveAdmins) {
                $this->auditLoginFailed($credentials['email'], $request, 'EMERGENCY_ADMIN_BLOCKED');
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }
        }

        // Locked account (too many failed attempts)
        if ($user && $user->isLocked()) {
            $this->auditLoginFailed($credentials['email'], $request, 'LOCKED');
            return response()->json([
                'message' => 'Account is locked due to too many failed login attempts. Contact your administrator.',
            ], 401);
        }

        // Inactive / not found — perform a dummy hash check to prevent timing-based
        // email enumeration (Hash::check takes ~100 ms; skipping it leaks existence).
        if (!$user || !$user->is_active) {
            Hash::check($credentials['password'], '$2y$12$invaliddummyhashvaluethatnevermatchesXXXXXXXXXXXXXXXXX');
            $this->auditLoginFailed($credentials['email'], $request, 'INACTIVE_OR_NOT_FOUND');
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Wrong password
        if (!Hash::check($credentials['password'], $user->password_hash)) {
            $this->recordFailedAttempt($user, $request);

            $freshUser = $user->fresh();
            if ($freshUser->isLocked()) {
                return response()->json([
                    'message' => 'Account locked after too many failed attempts. Contact your administrator.',
                ], 401);
            }

            $remaining = self::MAX_FAILED_ATTEMPTS - $freshUser->failed_login_attempts;
            return response()->json([
                'message'            => 'Invalid credentials.',
                'attempts_remaining' => max(0, $remaining),
            ], 401);
        }

        // Successful login — reset counters and issue token
        $token = $user->createToken('spa')->plainTextToken;

        $user->update([
            'failed_login_attempts'  => 0,
            'locked_at'              => null,
            'last_login_at'          => now(),
            'last_login_attempt_at'  => now(),
            'last_login_success'     => true,
        ]);

        AuditLog::create([
            'actor_id'    => $user->id,
            'action'      => 'AUTH_LOGIN',
            'after_state' => ['email' => $user->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        return response()->json(['user' => new UserResource($user), 'token' => $token]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * PATCH /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'   => ['nullable', 'string', 'max:100'],
            'last_name'    => ['nullable', 'string', 'max:100'],
            'name'         => ['nullable', 'string', 'max:200'],
            'organization' => ['nullable', 'string', 'max:200'],
            'org_role'     => ['nullable', 'string', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(new UserResource($user->fresh()));
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $policy = PasswordPolicy::find(1);

        $rules = ['required', 'string', 'confirmed'];
        if ($policy) {
            $pass = Password::min($policy->min_length);
            if ($policy->require_uppercase) $pass = $pass->mixedCase();
            if ($policy->require_number)    $pass = $pass->numbers();
            if ($policy->require_special)   $pass = $pass->symbols();
            $rules[] = $pass;
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => $rules,
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password_hash)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $historyCount = $policy?->history_count ?? 5;
        $recentHashes = PasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($historyCount)
            ->pluck('password_hash');

        foreach ($recentHashes as $oldHash) {
            if (Hash::check($data['password'], $oldHash)) {
                return response()->json([
                    'message' => "You cannot reuse your last {$historyCount} passwords.",
                ], 422);
            }
        }

        $newHash = Hash::make($data['password']);
        $user->update(['password_hash' => $newHash]);
        PasswordHistory::create(['user_id' => $user->id, 'password_hash' => $newHash]);

        AuditLog::create([
            'actor_id'   => $user->id,
            'action'     => 'AUTH_PASSWORD_CHANGED',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Increment failed_login_attempts and lock when threshold is reached. */
    private function recordFailedAttempt(User $user, Request $request): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $lockAt   = $attempts >= self::MAX_FAILED_ATTEMPTS ? now() : null;

        $user->update([
            'failed_login_attempts' => $attempts,
            'locked_at'             => $lockAt,
            'last_login_attempt_at' => now(),
            'last_login_success'    => false,
        ]);

        AuditLog::create([
            'actor_id'    => $user->id,
            'action'      => $lockAt ? 'AUTH_ACCOUNT_LOCKED' : 'AUTH_LOGIN_FAILED',
            'after_state' => ['email' => $user->email, 'attempt' => $attempts],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);
    }

    /** Audit a login failure with no associated user session. */
    private function auditLoginFailed(string $email, Request $request, string $reason): void
    {
        AuditLog::create([
            'action'      => 'AUTH_LOGIN_FAILED',
            'after_state' => ['email' => $email, 'reason' => $reason],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);
    }

    /**
     * POST /api/auth/sso-exchange
     * Exchanges the short-lived one-time SSO code for the real Sanctum token.
     * This avoids passing the raw token in the URL (and therefore in server logs).
     */
    public function ssoExchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $cacheKey = 'sso_exchange:' . $data['code'];
        $token    = cache()->pull($cacheKey); // pull = get + delete (one-time use)

        if (!$token) {
            return response()->json(['message' => 'Invalid or expired SSO code.'], 401);
        }

        return response()->json(['token' => $token]);
    }
}
