<?php

namespace App\Http\Controllers;

use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserSsoIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Handles SSO login via OIDC/OAuth2 providers (e.g. Microsoft Entra).
 *
 * Flow:
 *   1. Frontend calls GET /api/sso/{provider}/redirect
 *      → Returns { url } — frontend navigates browser to that URL
 *   2. IdP redirects to GET /api/sso/{provider}/callback?code=...&state=...
 *      → Validates state, exchanges code for token, fetches userinfo,
 *        finds or provisions the User, issues a Sanctum token, then redirects
 *        to /app?sso_code={one_time_code} — frontend exchanges via POST /api/auth/sso-exchange
 */
class SsoAuthController extends Controller
{
    /**
     * GET /api/sso/{provider}/redirect
     * Returns the authorization URL for the frontend to redirect to.
     */
    public function redirect(SsoProvider $provider)
    {
        if (!$provider->is_enabled) {
            return response()->json(['message' => 'SSO provider is disabled.'], 403);
        }

        $state       = Str::random(40);
        $redirectUri = $this->callbackUrl($provider->id);

        // Store state in cache keyed by provider for 10 min (stateless-friendly)
        cache()->put("sso_state:{$provider->id}:{$state}", true, 600);

        $url = $provider->buildAuthorizationUrl($state, $redirectUri);

        if (!$url) {
            return response()->json(['message' => 'Provider not properly configured.'], 422);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * GET /api/sso/{provider}/callback
     * Receives the OAuth2 callback from the IdP.
     */
    public function callback(Request $request, SsoProvider $provider)
    {
        $code  = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            Log::warning('SSO callback error', ['error' => $error, 'provider' => $provider->id]);
            // Sanitize the IdP-supplied error description before embedding in the redirect URL
            // to prevent phishing-quality content from being displayed to users.
            $rawDesc = $request->query('error_description', $error);
            $safeDesc = preg_replace('/[^a-zA-Z0-9 _.@\-]/', '', substr((string) $rawDesc, 0, 200));
            return redirect('/app?sso_error=' . urlencode($safeDesc ?: 'sso_error'));
        }

        // Validate state
        $cacheKey  = "sso_state:{$provider->id}:{$state}";
        $stateData = cache()->pull($cacheKey);
        if (!$stateData) {
            return redirect('/app?sso_error=invalid_state');
        }

        if (!$code) {
            return redirect('/app?sso_error=no_code');
        }

        try {
            $userInfo = $this->exchangeCodeForUserInfo($provider, $code);
        } catch (\Exception $e) {
            Log::error('SSO token exchange failed', ['error' => $e->getMessage()]);
            return redirect('/app?sso_error=token_exchange_failed');
        }

        $email = $userInfo['email'] ?? $userInfo['upn'] ?? $userInfo['preferred_username'] ?? null;
        $sub   = $userInfo['sub'] ?? $email;

        // ── "link" flow: associate identity with an already-authenticated user ──
        if (is_array($stateData) && ($stateData['action'] ?? '') === 'link') {
            $userId = $stateData['user_id'] ?? null;
            if ($userId) {
                UserSsoIdentity::updateOrCreate(
                    ['provider_id' => $provider->id, 'provider_sub' => $sub],
                    ['user_id' => $userId, 'provider_email' => $email]
                );
                return redirect('/app?sso_linked=1');
            }
        }

        if (!$email) {
            return redirect('/app?sso_error=no_email_in_token');
        }

        // Find or provision user
        $user = User::where('email', $email)->first();

        if (!$user) {
            if (!$provider->auto_provision_users) {
                return redirect('/app?sso_error=account_not_found');
            }

            $user = User::create([
                'email'      => $email,
                'first_name' => $userInfo['given_name'] ?? $userInfo['name'] ?? 'SSO',
                'last_name'  => $userInfo['family_name'] ?? 'User',
                'name'       => $userInfo['name'] ?? $email,
                'roles'      => [$provider->default_role],
                'status'     => 'active',
                'password'   => bcrypt(Str::random(32)), // random; user will use SSO
            ]);
        }

        if (!$user->is_active) {
            return redirect('/app?sso_error=account_inactive');
        }

        // Issue Sanctum token
        $token = $user->createToken('sso-login')->plainTextToken;

        // Store or refresh SSO identity record
        UserSsoIdentity::updateOrCreate(
            ['provider_id' => $provider->id, 'provider_sub' => $userInfo['sub'] ?? $email],
            ['user_id' => $user->id, 'provider_email' => $email]
        );

        // Use a short-lived one-time code instead of putting the raw token in the URL.
        // The frontend exchanges this code for the real token via POST /api/auth/sso-exchange.
        $code = \Illuminate\Support\Str::random(64);
        cache()->put("sso_exchange:{$code}", $token, 60); // valid for 60 seconds

        return redirect('/app?sso_code=' . urlencode($code));
    }

    // ── SSO identity management ───────────────────────────────────────────────

    /**
     * GET /api/auth/sso/identities
     * List the authenticated user's linked SSO identities.
     */
    public function listIdentities(Request $request): JsonResponse
    {
        $identities = UserSsoIdentity::with('provider:id,name,protocol')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn($i) => [
                'id'             => $i->id,
                'provider_id'    => $i->provider_id,
                'provider_name'  => $i->provider?->name,
                'provider_email' => $i->provider_email,
                'linked_at'      => $i->linked_at,
            ]);

        return response()->json(['data' => $identities]);
    }

    /**
     * POST /api/auth/sso/link
     * Initiates SSO flow to link an SSO provider to the current user account.
     * Returns { url } — frontend navigates the browser there.
     */
    public function initiateLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'uuid', 'exists:sso_providers,id'],
        ]);

        $provider = SsoProvider::findOrFail($data['provider_id']);

        if (!$provider->is_enabled) {
            return response()->json(['message' => 'SSO provider is disabled.'], 422);
        }

        $state       = Str::random(40);
        $redirectUri = $this->callbackUrl($provider->id);

        // Tag state as a "link" operation with the current user's ID
        cache()->put("sso_state:{$provider->id}:{$state}", [
            'action'  => 'link',
            'user_id' => $request->user()->id,
        ], 600);

        $url = $provider->buildAuthorizationUrl($state, $redirectUri);

        if (!$url) {
            return response()->json(['message' => 'Provider not properly configured.'], 422);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * DELETE /api/auth/sso/identities/{identity}
     * Unlink an SSO identity from the authenticated user.
     */
    public function unlinkIdentity(Request $request, UserSsoIdentity $identity): JsonResponse
    {
        if ($identity->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $identity->delete();

        return response()->json(null, 204);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function callbackUrl(string $providerId): string
    {
        return rtrim(config('app.url'), '/') . "/api/sso/{$providerId}/callback";
    }

    /**
     * Exchange authorization code for user info via OIDC token endpoint.
     */
    private function exchangeCodeForUserInfo(SsoProvider $provider, string $code): array
    {
        $cfg         = $provider->decryptedConfig();
        $redirectUri = $this->callbackUrl($provider->id);

        // Determine token endpoint
        $tokenUrl = $cfg['token_url']
            ?? "https://login.microsoftonline.com/{$cfg['tenant_id']}/oauth2/v2.0/token";

        // Exchange code for tokens
        $response = Http::asForm()->post($tokenUrl, [
            'grant_type'    => 'authorization_code',
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
            'scope'         => $cfg['scopes'] ?? 'openid email profile',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Token endpoint returned: ' . $response->status() . ' ' . $response->body());
        }

        $tokens = $response->json();

        // Try userinfo endpoint first
        $userinfoUrl = $cfg['userinfo_url'] ?? null;

        if ($userinfoUrl) {
            $uiResp = Http::withToken($tokens['access_token'])->get($userinfoUrl);
            if ($uiResp->successful()) {
                return $uiResp->json();
            }
        }

        // Fall back to decoding the ID token (JWT, no signature verification needed here
        // since we trust the IdP — we verified via state + client_secret exchange)
        if (!empty($tokens['id_token'])) {
            $parts   = explode('.', $tokens['id_token']);
            $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
            return $payload ?? [];
        }

        return [];
    }
}
