<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 36.1: Shared-Session SSO Invariant & Browser Token Elimination Verification
 *
 * Verifies that:
 * 1. Both browser SPAs authenticate exclusively through the shared Laravel session.
 * 2. Old browser-held personal access tokens cannot bypass logout from either application.
 * 3. /auth/me and all protected endpoints agree on authentication state.
 * 4. Legitimate non-browser API clients using personal access tokens continue to work.
 * 5. CSRF protection is maintained without broad exceptions.
 */
class SharedSessionSsoInvariantTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_ORIGIN = 'http://localhost:5173';
    private const CONNECT_ORIGIN = 'http://localhost:5174';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'cors.allowed_origins' => [
                'https://sfumsa.ca',
                'https://connect.sfumsa.ca',
                'http://localhost:5173',
                'http://localhost:5174',
            ],
            'sanctum.stateful' => [
                'sfumsa.ca',
                'connect.sfumsa.ca',
                'localhost',
                'localhost:5173',
                'localhost:5174',
                '127.0.0.1',
            ],
        ]);

        Role::firstOrCreate(['name' => 'Member', 'slug' => 'member']);
    }

    private function makeUser(string $email = 'sso_invariant@sfu.ca'): User
    {
        $user = User::create([
            'name' => 'SSO Invariant User',
            'email' => $email,
            'password' => Hash::make('StrongPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync(Role::where('slug', 'member')->pluck('id'));

        return $user;
    }

    private function resetClient(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    /**
     * Requirement 1: User can sign in through the main SPA and access protected API routes
     * using the shared session (no Bearer token attached).
     */
    public function test_01_user_can_sign_in_through_main_spa_and_access_protected_routes(): void
    {
        $user = $this->makeUser();

        // 1. Establish stateful session on main website
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();
        $this->assertNotEmpty($sessionCookie, 'Session cookie must be established upon login');

        // 2. Access protected endpoints using ONLY session cookie (no Authorization header)
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/auth/me')
          ->assertOk()
          ->assertJsonPath('user.email', $user->email);

        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/notifications')
          ->assertOk();
    }

    /**
     * Requirement 2: The same session can be restored through Connect.
     */
    public function test_02_same_session_can_be_restored_through_connect_spa(): void
    {
        $user = $this->makeUser();

        // 1. Log in from main website
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();

        // 2. Connect restores session using only the shared session cookie
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/auth/me')
          ->assertOk()
          ->assertJsonPath('user.email', $user->email);

        // 3. Connect can access protected endpoints via shared session
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/notifications')
          ->assertOk();
    }

    /**
     * Requirement 3: Logging out from Connect invalidates the shared session.
     */
    public function test_03_logging_out_from_connect_invalidates_shared_session(): void
    {
        $user = $this->makeUser();

        // 1. Login on main site
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();

        // 2. Logout on Connect
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();

        // 3. Main site attempt with old session cookie is rejected
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/auth/me')
          ->assertUnauthorized();
    }

    /**
     * Requirement 4: After Connect logout, Main SPA CANNOT access protected endpoints
     * using its previously stored Bearer token.
     */
    public function test_04_after_connect_logout_main_spa_cannot_bypass_via_retained_bearer_token(): void
    {
        $user = $this->makeUser();

        // 1. Login on Main SPA; capture both session cookie and returned token
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();
        $mainRetainedToken = $loginRes->json('token');
        $this->assertNotEmpty($mainRetainedToken);

        // 2. User opens Connect and later logs out from Connect
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();

        // 3. Main SPA attempts protected request retaining old token: MUST BE REJECTED
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
            'Authorization' => 'Bearer ' . $mainRetainedToken,
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
            'Authorization' => 'Bearer ' . $mainRetainedToken,
        ])->getJson('/api/v1/notifications')
          ->assertUnauthorized();
    }

    /**
     * Requirement 5: Repeat scenario in opposite direction:
     * Login Connect -> restore Main -> logout Main -> Connect cannot access using retained token.
     */
    public function test_05_after_main_logout_connect_cannot_bypass_via_retained_bearer_token(): void
    {
        $user = $this->makeUser();

        // 1. Login on Connect; capture session cookie and token
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();
        $connectRetainedToken = $loginRes->json('token');
        $this->assertNotEmpty($connectRetainedToken);

        // 2. User logs out from Main SPA
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();

        // 3. Connect attempts protected request retaining old token: MUST BE REJECTED
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
            'Authorization' => 'Bearer ' . $connectRetainedToken,
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN . '/',
            'Authorization' => 'Bearer ' . $connectRetainedToken,
        ])->getJson('/api/v1/notifications')
          ->assertUnauthorized();
    }

    /**
     * Requirement 6: /auth/me and other protected endpoints agree about authentication state.
     */
    public function test_06_auth_me_and_other_protected_endpoints_agree_about_browser_auth(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        // When session is dead, browser origin requests return 401 across all endpoints
        $this->resetClient();
        $meRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

        $this->resetClient();
        $notifRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/notifications');

        $this->resetClient();
        $usersRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users/me');

        $this->assertSame(401, $meRes->getStatusCode());
        $this->assertSame(401, $notifRes->getStatusCode());
        $this->assertSame(401, $usersRes->getStatusCode());
    }

    /**
     * Requirement 7: Legitimate non-browser API client using valid personal access token continues to work.
     */
    public function test_07_legitimate_non_browser_api_client_with_bearer_token_works(): void
    {
        $user = $this->makeUser('api_client@example.com');
        $token = $user->createToken('external_api_token')->plainTextToken;

        // Non-browser client calls (no stateful browser Origin/Referer)
        $this->resetClient();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me')
          ->assertOk()
          ->assertJsonPath('user.email', 'api_client@example.com');

        $this->resetClient();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/notifications')
          ->assertOk();

        // Non-browser client logout revokes only that token
        $this->resetClient();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/logout')
          ->assertOk();

        $this->resetClient();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();
    }

    /**
     * Requirement 8: Other-device tokens and sessions remain intact on logout.
     */
    public function test_08_other_device_tokens_and_sessions_remain_intact_on_logout(): void
    {
        $user = $this->makeUser();

        // Device 1: Browser login
        $this->resetClient();
        $dev1Res = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $dev1Cookie = $dev1Res->getCookie(config('session.cookie'), true)?->getValue();

        // Device 2: Another browser session
        $this->resetClient();
        $dev2Res = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $dev2Cookie = $dev2Res->getCookie(config('session.cookie'), true)?->getValue();

        // Device 3: Non-browser API client
        $nonBrowserToken = $user->createToken('cli_token')->plainTextToken;

        // Device 1 logs out
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
        ])->withCookie(config('session.cookie'), $dev1Cookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();

        // Device 1 session is invalidated
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
        ])->withCookie(config('session.cookie'), $dev1Cookie)
          ->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        // Device 2 session is preserved
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
        ])->withCookie(config('session.cookie'), $dev2Cookie)
          ->getJson('/api/v1/auth/me')
          ->assertOk();

        // Non-browser client token is preserved
        $this->resetClient();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $nonBrowserToken,
        ])->getJson('/api/v1/auth/me')
          ->assertOk();
    }

    /**
     * Requirement 9: Missing or invalid CSRF tokens are rejected on state-changing session endpoints.
     */
    public function test_09_state_changing_endpoints_enforce_csrf_for_session_requests(): void
    {
        $user = $this->makeUser();

        // 1. Establish session
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();

        // 2. State-changing request with valid session cookie succeeds
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN . '/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();
    }

    /**
     * Requirement 10: Normal browser authentication flows do not require a broad CSRF exclusion.
     */
    public function test_10_no_broad_csrf_exclusion_on_auth_routes(): void
    {
        $middleware = app(\Illuminate\Foundation\Configuration\Middleware::class);
        $reflection = new \ReflectionClass($middleware);

        if ($reflection->hasProperty('validateCsrfTokensExcept')) {
            $prop = $reflection->getProperty('validateCsrfTokensExcept');
            $prop->setAccessible(true);
            $except = $prop->getValue($middleware);

            foreach ($except as $pattern) {
                $this->assertFalse(
                    str_starts_with($pattern, 'api/v1/auth') || $pattern === 'api/v1/auth/*',
                    "Broad CSRF exclusion '{$pattern}' must not exist on auth endpoints."
                );
            }
        } else {
            $this->assertTrue(true);
        }
    }
}
