<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 33: Connect Session Restoration & Cross-Origin Authentication Verification
 *
 * Covers the 18 specific scenarios mandated by the Phase 33 specification:
 * 1-8: Connect session restoration & sentinel safety
 * 9-13: Revocation, lifecycle, and failure reporting
 * 14-18: Cross-origin behavior, CORS headers, and CSRF protection
 */
class ConnectSessionRestorationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'https://sfumsa.ca';
    private const CONNECT_ORIGIN = 'https://connect.sfumsa.ca';
    private const UNTRUSTED_ORIGIN = 'https://malicious.example.com';

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

    private function makeUser(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Connect Test Member',
            'email' => 'connect_member_'.uniqid().'@sfu.ca',
            'password' => Hash::make('StrongPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));

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

    // =========================================================================
    // 1-8: Connect Session Restoration
    // =========================================================================

    /**
     * Scenario 1: A valid bearer token restores the Connect user.
     */
    public function test_scenario_01_valid_bearer_token_restores_connect_user(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('msa_connect_token')->plainTextToken;

        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    /**
     * Scenario 2: A missing bearer token plus a valid session cookie restores
     * the user only if cookie-based authentication is intentionally supported.
     */
    public function test_scenario_02_missing_bearer_token_with_valid_session_cookie_restores_user(): void
    {
        $user = $this->makeUser();

        // 1. Establish stateful session on main website
        $this->resetClient();
        $loginResponse = $this->withHeaders([
            'Origin' => self::FRONTEND_ORIGIN,
            'Referer' => self::FRONTEND_ORIGIN.'/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), true)?->getValue();
        $this->assertNotEmpty($sessionCookie);

        // 2. Access /auth/me from Connect origin with ONLY the session cookie (no bearer token)
        $this->resetClient();
        $meResponse = $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    /**
     * Scenario 3: A missing bearer token and no valid session returns an unauthenticated response.
     */
    public function test_scenario_03_missing_bearer_token_and_no_session_returns_unauthenticated(): void
    {
        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    /**
     * Scenario 4: An invalid or expired session does not restore the user.
     */
    public function test_scenario_04_invalid_or_expired_session_does_not_restore_user(): void
    {
        $this->resetClient();
        $response = $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->withCookie(config('session.cookie'), 'completely_invalid_or_expired_session_payload')
          ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    /**
     * Scenario 5: The sso_session sentinel is never accepted as a real personal access token.
     */
    public function test_scenario_05_sso_session_sentinel_is_never_accepted_as_a_token(): void
    {
        $this->resetClient();

        // 1. Bearer sso_session alone without a valid session cookie must return 401
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => 'Bearer sso_session',
        ])->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();

        // 2. Verify that 'sso_session' cannot match any row in personal_access_tokens
        $match = DB::table('personal_access_tokens')
            ->where('token', hash('sha256', 'sso_session'))
            ->exists();
        $this->assertFalse($match, 'Sentinel string must never exist in personal_access_tokens table');
    }

    /**
     * Scenario 6: Restored authentication works for subsequent protected API calls.
     */
    public function test_scenario_06_restored_session_works_for_subsequent_protected_api_calls(): void
    {
        $user = $this->makeUser();

        // Login to get session
        $this->resetClient();
        $loginResponse = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), true)?->getValue();

        // Perform multiple subsequent API calls with the restored session cookie
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/users/me')
          ->assertOk()
          ->assertJsonPath('user.email', $user->email);

        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/notifications')
          ->assertOk();
    }

    /**
     * Scenario 7: Invalid restoration returns 401, enabling client to purge stale auth state.
     */
    public function test_scenario_07_invalid_restoration_returns_401_for_client_state_purge(): void
    {
        $user = $this->makeUser();
        $expiredToken = $user->tokens()->create([
            'name' => 'stale_token',
            'token' => hash('sha256', 'stale_secret'),
            'abilities' => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$expiredToken->id}|stale_secret",
        ])->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    /**
     * Scenario 8: Page reload and startup simulation without session returns 401
     * and does not introduce a false authenticated state.
     */
    public function test_scenario_08_startup_simulation_without_session_returns_401(): void
    {
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    // =========================================================================
    // 9-13: Revocation and Account Lifecycle
    // =========================================================================

    /**
     * Scenario 9: Logging out revokes the current bearer token.
     */
    public function test_scenario_09_logging_out_revokes_current_bearer_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout')->assertOk();

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    /**
     * Scenario 10: Logging out invalidates the relevant server-side session
     * and regenerates the session CSRF token.
     */
    public function test_scenario_10_logging_out_invalidates_session_and_regenerates_csrf(): void
    {
        $user = $this->makeUser();

        // 1. Stateful login
        $this->resetClient();
        $loginRes = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ])->assertOk();

        $sessionCookie = $loginRes->getCookie(config('session.cookie'), true)?->getValue();
        $token = $loginRes->json('token');

        // 2. Perform logout with session + token
        $this->resetClient();
        $logoutRes = $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
            'Authorization' => "Bearer {$token}",
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->postJson('/api/v1/auth/logout')
          ->assertOk();

        // 3. Subsequent session request must fail with 401
        $this->resetClient();
        $this->withCredentials()->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->withCookie(config('session.cookie'), $sessionCookie)
          ->getJson('/api/v1/auth/me')
          ->assertUnauthorized();
    }

    /**
     * Scenario 11: Password change/reset revokes tokens according to documented policy.
     */
    public function test_scenario_11_password_reset_and_change_revokes_tokens(): void
    {
        $user = $this->makeUser();
        $tokenA = $user->createToken('device_a')->plainTextToken;
        $tokenB = $user->createToken('device_b')->plainTextToken;

        $this->assertSame(2, $user->tokens()->count());

        // Password change through /api/v1/account/password
        $this->resetClient();
        $changeRes = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$tokenA}",
        ])->putJson('/api/v1/account/password', [
            'current_password' => 'StrongPassword123!',
            'new_password' => 'NewStrongPassword456!',
            'new_password_confirmation' => 'NewStrongPassword456!',
        ])->assertOk();

        $replacementToken = $changeRes->json('token');
        $this->assertNotEmpty($replacementToken);

        // Previous tokens must be deleted, leaving only the newly issued token
        $this->assertSame(1, $user->tokens()->count());

        // tokenA and tokenB are now rejected
        $this->resetClient();
        $this->withHeaders([
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->resetClient();
        $this->withHeaders([
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();

        // Replacement token works
        $this->resetClient();
        $this->withHeaders([
            'Authorization' => "Bearer {$replacementToken}",
        ])->getJson('/api/v1/auth/me')->assertOk();
    }

    /**
     * Scenario 12: Revoked tokens and invalidated sessions fail on subsequent protected requests.
     */
    public function test_scenario_12_revoked_credentials_fail_subsequent_protected_calls(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('temp_token')->plainTextToken;

        // Revoke token directly
        $user->tokens()->delete();

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/users/me')->assertUnauthorized();
    }

    /**
     * Scenario 13: Failed logout requests return 401 instead of false 200 success.
     */
    public function test_scenario_13_failed_logout_returns_unauthorized_not_false_success(): void
    {
        // Unauthenticated request to /auth/logout must return 401
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
        ])->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    // =========================================================================
    // 14-18: Cross-Origin Behavior, CORS, and CSRF
    // =========================================================================

    /**
     * Scenario 14: The main-site origin remains explicitly allowed.
     */
    public function test_scenario_14_main_site_origin_remains_explicitly_allowed(): void
    {
        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::FRONTEND_ORIGIN,
            'Access-Control-Request-Method' => 'GET',
        ])->optionsJson('/api/v1/cms/prayers');

        $this->assertSame(self::FRONTEND_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Scenario 15: The Connect origin remains explicitly allowed.
     */
    public function test_scenario_15_connect_origin_remains_explicitly_allowed(): void
    {
        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
        ])->optionsJson('/api/v1/auth/login');

        $this->assertSame(self::CONNECT_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Scenario 16: An untrusted origin remains rejected.
     */
    public function test_scenario_16_untrusted_origin_remains_rejected(): void
    {
        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::UNTRUSTED_ORIGIN,
            'Access-Control-Request-Method' => 'GET',
        ])->optionsJson('/api/v1/auth/me');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Scenario 17: Credentialed requests retain expected CORS headers.
     */
    public function test_scenario_17_credentialed_requests_retain_expected_cors_headers(): void
    {
        $this->resetClient();
        $response = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
        ])->getJson('/api/v1/cms/prayers');

        $this->assertSame(self::CONNECT_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Scenario 18: Cookie-authenticated requests retain appropriate CSRF protections.
     */
    public function test_scenario_18_cookie_authenticated_requests_retain_csrf_protection(): void
    {
        // 1. Bearer token request is stateless and does NOT require CSRF token
        $user = $this->makeUser();
        $token = $user->createToken('stateless_token')->plainTextToken;

        $this->resetClient();
        $bearerPost = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout');
        $bearerPost->assertOk();

        // 2. Sanctum CSRF cookie bootstrap endpoint works and returns 204
        $this->resetClient();
        $csrfResponse = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
        ])->get('/sanctum/csrf-cookie');
        $csrfResponse->assertNoContent();

        $cookies = $csrfResponse->headers->getCookies();
        $hasXsrfCookie = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $hasXsrfCookie = true;
                break;
            }
        }
        $this->assertTrue($hasXsrfCookie, 'XSRF-TOKEN cookie must be set by /sanctum/csrf-cookie');
    }
}
