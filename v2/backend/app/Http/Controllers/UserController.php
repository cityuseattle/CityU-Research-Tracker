<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\CoordinatorGroupAssignment;
use App\Models\Group;
use App\Models\PasswordHistory;
use App\Models\PasswordPolicy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Paginated list with optional search + role + lock filter.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('program')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(organization) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            })
            ->when($request->input('role'), function ($q, $role) {
                $q->whereRaw("roles @> ?", [json_encode([$role])]);
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->input('locked') === 'true', function ($q) {
                $q->whereNotNull('locked_at');
            })
            ->orderBy('name');

        $perPage = min($request->integer('per_page', 20), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $user->load('program', 'groups');
        return response()->json(new UserResource($user));
    }

    /**
     * POST /api/users
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $policy = PasswordPolicy::find(1);
        $passwordRules = $this->buildPasswordRules($policy);

        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'organization' => ['nullable', 'string', 'max:255'],
            'org_role'     => ['nullable', 'string', 'max:100'],
            'password'     => array_merge(['required', 'string', 'confirmed'], $passwordRules),
            'roles'        => ['required', 'array', 'min:1'],
            'roles.*'      => ['required', Rule::in(['admin', 'coordinator', 'reviewer', 'student'])],
            'program_id'   => ['nullable', 'uuid', 'exists:programs,id'],
            'is_active'    => ['boolean'],
        ]);

        // Coordinators cannot create admin accounts
        $actor = $request->user();
        if ($actor->hasRole('coordinator') && !$actor->hasRole('admin')) {
            $data['roles'] = array_values(array_filter($data['roles'], fn($r) => $r !== 'admin'));
            if (empty($data['roles'])) {
                $data['roles'] = ['student'];
            }
        }

        $user = User::create([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'name'          => trim($data['first_name'] . ' ' . $data['last_name']),
            'email'         => $data['email'],
            'organization'  => $data['organization'] ?? null,
            'org_role'      => $data['org_role'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'roles'         => $data['roles'],
            'program_id'    => $data['program_id'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
        ]);

        PasswordHistory::create([
            'user_id'       => $user->id,
            'password_hash' => $user->password_hash,
        ]);

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'action'      => 'USER_CREATED',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'after_state' => ['email' => $user->email, 'roles' => $user->roles],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        User::syncEmergencyAdmin();

        return response()->json(new UserResource($user->load('program')), 201);
    }

    /**
     * PATCH /api/users/{user}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        // Prevent editing the emergency admin's core fields
        if ($user->is_emergency_admin) {
            return response()->json(['message' => 'The emergency admin account cannot be modified directly.'], 403);
        }

        $actor   = $request->user();
        $isAdmin = $actor->hasRole('admin');
        $isCoordinatorOnly = $actor->hasRole('coordinator') && !$isAdmin;

        // Coordinators can only update organisation and org_role and active status
        if ($isCoordinatorOnly) {
            $data = $request->validate([
                'organization' => ['sometimes', 'nullable', 'string', 'max:255'],
                'org_role'     => ['sometimes', 'nullable', 'string', 'max:100'],
                'is_active'    => ['sometimes', 'boolean'],
                'program_id'   => ['sometimes', 'nullable', 'uuid', 'exists:programs,id'],
            ]);

            $before = $user->only(['is_active', 'organization', 'org_role', 'program_id']);
            $user->update($data);

            AuditLog::create([
                'actor_id'     => $actor->id,
                'action'       => 'USER_UPDATED',
                'target_type'  => 'user',
                'target_id'    => $user->id,
                'before_state' => $before,
                'after_state'  => $user->only(['is_active', 'organization', 'org_role', 'program_id']),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'request_id'   => $request->header('X-Request-Id'),
            ]);

            return response()->json(new UserResource($user->load('program')));
        }

        $rules = [
            'first_name'   => ['sometimes', 'string', 'max:255'],
            'last_name'    => ['sometimes', 'string', 'max:255'],
            'email'        => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'organization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'org_role'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'program_id'   => ['sometimes', 'nullable', 'uuid', 'exists:programs,id'],
            'is_active'    => ['sometimes', 'boolean'],
        ];

        if ($isAdmin) {
            $rules['roles']   = ['sometimes', 'array', 'min:1'];
            $rules['roles.*'] = ['required', Rule::in(['admin', 'coordinator', 'reviewer', 'student'])];
        }

        $data = $request->validate($rules);

        if (!$isAdmin) {
            unset($data['roles'], $data['is_active']);
        }

        // Keep name in sync
        $firstName = $data['first_name'] ?? $user->first_name;
        $lastName  = $data['last_name']  ?? $user->last_name;
        $data['name'] = trim($firstName . ' ' . $lastName);

        $before = $user->only(['name', 'email', 'roles', 'is_active', 'organization', 'org_role']);
        $user->update($data);

        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => 'USER_UPDATED',
            'target_type'  => 'user',
            'target_id'    => $user->id,
            'before_state' => $before,
            'after_state'  => $user->only(['name', 'email', 'roles', 'is_active', 'organization', 'org_role']),
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'request_id'   => $request->header('X-Request-Id'),
        ]);

        User::syncEmergencyAdmin();

        return response()->json(new UserResource($user->load('program')));
    }

    /**
     * DELETE /api/users/{user}
     * Deactivates the user (soft delete).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->is_emergency_admin) {
            return response()->json(['message' => 'The emergency admin account cannot be deleted.'], 403);
        }

        $user->update(['is_active' => false]);

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'action'      => 'USER_DEACTIVATED',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'after_state' => ['email' => $user->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        User::syncEmergencyAdmin();

        return response()->json(['message' => 'User deactivated.']);
    }

    /**
     * POST /api/users/{user}/activate
     */
    public function activate(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->is_emergency_admin) {
            return response()->json(['message' => 'The emergency admin account is managed automatically.'], 403);
        }

        $user->update(['is_active' => true]);

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'action'      => 'USER_ACTIVATED',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'after_state' => ['email' => $user->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        User::syncEmergencyAdmin();

        return response()->json(['message' => 'User activated.']);
    }

    /**
     * POST /api/users/{user}/unlock
     * Admin unlocks a locked account (clears locked_at and resets failed attempts).
     */
    public function unlock(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user->update([
            'locked_at'              => null,
            'failed_login_attempts'  => 0,
        ]);

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'action'      => 'USER_UNLOCKED',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'after_state' => ['email' => $user->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        return response()->json(new UserResource($user->load('program')));
    }

    /**
     * POST /api/users/{user}/reset-password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        $policy = PasswordPolicy::find(1);
        $passwordRules = $this->buildPasswordRules($policy);

        $data = $request->validate([
            'password' => array_merge(['required', 'string', 'confirmed'], $passwordRules),
        ]);

        if ($policy && $policy->history_count > 0) {
            $history = PasswordHistory::where('user_id', $user->id)
                ->latest()->take($policy->history_count)->pluck('password_hash');

            foreach ($history as $old) {
                if (Hash::check($data['password'], $old)) {
                    return response()->json([
                        'message' => 'Password was recently used. Please choose a different password.',
                        'errors'  => ['password' => ['Password was recently used.']],
                    ], 422);
                }
            }
        }

        $hash = Hash::make($data['password']);
        $user->update(['password_hash' => $hash]);

        PasswordHistory::create(['user_id' => $user->id, 'password_hash' => $hash]);

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'action'      => 'USER_PASSWORD_RESET',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'after_state' => ['reset_by' => $request->user()->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'request_id'  => $request->header('X-Request-Id'),
        ]);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * PATCH /api/users/{user}/roles
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignRoles', User::class);

        if ($user->is_emergency_admin) {
            return response()->json(['message' => 'Cannot change the emergency admin roles.'], 403);
        }

        $data = $request->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(['admin', 'coordinator', 'reviewer', 'student'])],
        ]);

        $before = $user->roles;
        $user->update(['roles' => $data['roles']]);

        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => 'USER_ROLES_CHANGED',
            'target_type'  => 'user',
            'target_id'    => $user->id,
            'before_state' => ['roles' => $before],
            'after_state'  => ['roles' => $user->roles],
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'request_id'   => $request->header('X-Request-Id'),
        ]);

        User::syncEmergencyAdmin();

        return response()->json(new UserResource($user));
    }

    // ── Coordinator group scope ────────────────────────────────────────────────

    /**
     * GET /api/users/{user}/coordinator-groups
     */
    public function coordinatorGroups(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $groups = CoordinatorGroupAssignment::with('group:id,name,type,slug')
            ->where('coordinator_id', $user->id)
            ->get()
            ->map(fn($a) => [
                'id'          => $a->group->id,
                'name'        => $a->group->name,
                'type'        => $a->group->type,
                'slug'        => $a->group->slug,
                'assigned_at' => $a->assigned_at,
            ]);

        return response()->json(['data' => $groups]);
    }

    /**
     * POST /api/users/{user}/coordinator-groups
     */
    public function addCoordinatorGroup(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'group_id' => ['required', 'uuid', 'exists:groups,id'],
        ]);

        if (!in_array('coordinator', $user->roles ?? [])) {
            return response()->json(['message' => 'User does not have the coordinator role.'], 422);
        }

        CoordinatorGroupAssignment::firstOrCreate([
            'coordinator_id' => $user->id,
            'group_id'       => $data['group_id'],
        ]);

        return $this->coordinatorGroups($user);
    }

    /**
     * DELETE /api/users/{user}/coordinator-groups/{group}
     */
    public function removeCoordinatorGroup(Request $request, User $user, Group $group): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        CoordinatorGroupAssignment::where('coordinator_id', $user->id)
            ->where('group_id', $group->id)
            ->delete();

        return response()->json(null, 204);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildPasswordRules(?PasswordPolicy $policy): array
    {
        if (!$policy) return [];

        $rule = Password::min($policy->min_length);
        if ($policy->require_uppercase) $rule = $rule->mixedCase();
        if ($policy->require_number)    $rule = $rule->numbers();
        if ($policy->require_special)   $rule = $rule->symbols();

        return [$rule];
    }
}
