<?php

namespace Tests\Feature\Auth;

use App\Models\AppAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Independent Token Authentication Invariant Tests
 *
 * Verifies that shared-session SSO is cancelled/deferred and independent Sanctum
 * Bearer-token authentication is restored as the intended architecture:
 * 1. Login returns a usable token for browser origins.
 * 2. A valid Bearer token authenticates /auth/me and protected API endpoints.
 * 3. Missing, invalid, and revoked tokens receive HTTP 401.
 * 4. Logout revokes the current token.
 * 5. Independent token preservation: logging out of one app (e.g. Connect) does NOT
 *    invalidate the other app's token (e.g. Main SPA).
 * 6. The API login flow does not return 419 due to stateful CSRF requirements.
 * 7. CSRF protection has not been broadly disabled for routes that genuinely require it.
 * 8. Existing role and permission checks remain enforced.
 */
class IndependentTokenAuthInvariantTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_ORIGIN = 'https://sfumsa.ca';
    private const CONNECT_ORIGIN = 'https://connect.sfumsa.ca';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Member', 'slug' => 'member']);
        Role::firstOrCreate(['name' => 'Volunteer', 'slug' => 'volunteer']);
        Role::firstOrCreate(['name' => 'Super Admin', 'slug' => 'super-admin']);
    }

    private function createUser(string $email = 'user@sfu.ca', string $role = 'member'): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $roleModel = Role::where('slug', $role)->first();
        if ($roleModel) {
            $user->roles()->sync([$roleModel->id]);
        }

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
     * Requirement 1: Login returns a usable personal access token from both browser origins.
     */
    public function test_login_returns_a_usable_token_for_both_browser_origins(): void
    {
        $user = $this->createUser();

        foreach ([self::MAIN_ORIGIN, self::CONNECT_ORIGIN] as $origin) {
            $this->resetClient();
            $response = $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/login',
            ])->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

            $response->assertOk()
                ->assertJsonStructure(['message', 'user', 'token']);

            $token = $response->json('token');
            $this->assertIsString($token);
            $this->assertNotEmpty($token);

            // Verify the token immediately authenticates /auth/me
            $this->resetClient();
            $meResponse = $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/auth/me');

            $meResponse->assertOk()
                ->assertJsonPath('user.email', $user->email);
        }
    }

    /**
     * Requirement 2: A valid Bearer token authenticates /auth/me and protected API endpoints.
     */
    public function test_valid_bearer_token_authenticates_auth_me_and_protected_api_endpoints(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test_token')->plainTextToken;

        foreach ([self::MAIN_ORIGIN, self::CONNECT_ORIGIN] as $origin) {
            $this->resetClient();
            $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/auth/me')
              ->assertOk()
              ->assertJsonPath('user.email', $user->email);

            $this->resetClient();
            $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/users/me')
              ->assertOk()
              ->assertJsonPath('user.email', $user->email);

            $this->resetClient();
            $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/notifications')
              ->assertOk();
        }
    }

    /**
     * Requirement 3: Missing, invalid, and revoked tokens receive HTTP 401.
     */
    public function test_missing_invalid_and_revoked_tokens_receive_http_401(): void
    {
        $user = $this->createUser();

        // 1. Missing token
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        // 2. Invalid / malformed token
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => 'Bearer completely_bogus_token_12345',
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        // 3. Revoked token
        $token = $user->createToken('to_be_revoked')->plainTextToken;
        $user->tokens()->delete();

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();
    }

    /**
     * Requirement 4: Logout revokes the current token.
     */
    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('logout_test_token')->plainTextToken;

        // Logout with token
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout')
          ->assertOk();

        // Subsequent request is unauthorized
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();
    }

    /**
     * Requirement 5: A token from one app remains valid when the other app logs out,
     * because the apps use independent tokens.
     */
    public function test_token_from_one_app_remains_valid_when_the_other_app_logs_out(): void
    {
        $user = $this->createUser();

        // Main app logs in and acquires its independent token
        $this->resetClient();
        $mainLogin = $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN.'/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();
        $mainToken = $mainLogin->json('token');
        $this->assertNotEmpty($mainToken);

        // Connect app logs in and acquires its independent token
        $this->resetClient();
        $connectLogin = $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/login',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();
        $connectToken = $connectLogin->json('token');
        $this->assertNotEmpty($connectToken);

        $this->assertNotEquals($mainToken, $connectToken);
        $this->assertSame(2, $user->tokens()->count());

        // Connect logs out
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
            'Authorization' => "Bearer {$connectToken}",
        ])->postJson('/api/v1/auth/logout')
          ->assertOk();

        // Connect token is now revoked
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::CONNECT_ORIGIN,
            'Referer' => self::CONNECT_ORIGIN.'/',
            'Authorization' => "Bearer {$connectToken}",
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        // Main token REMAINS VALID and active
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN.'/',
            'Authorization' => "Bearer {$mainToken}",
        ])->getJson('/api/v1/auth/me')
          ->assertOk()
          ->assertJsonPath('user.email', $user->email);

        $this->assertSame(1, $user->tokens()->count());

        // Now Main logs out
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN.'/',
            'Authorization' => "Bearer {$mainToken}",
        ])->postJson('/api/v1/auth/logout')
          ->assertOk();

        // Main token is now also revoked
        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Referer' => self::MAIN_ORIGIN.'/',
            'Authorization' => "Bearer {$mainToken}",
        ])->getJson('/api/v1/auth/me')
          ->assertUnauthorized();

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Requirement 6: The API's login flow no longer returns 419 due to an unnecessary
     * stateful-session CSRF requirement.
     */
    public function test_api_login_flow_does_not_return_419_due_to_stateful_session_csrf(): void
    {
        $user = $this->createUser();

        // Send login POST without CSRF token or session cookie from browser origins
        foreach ([self::MAIN_ORIGIN, self::CONNECT_ORIGIN] as $origin) {
            $this->resetClient();
            $response = $this->withHeaders([
                'Origin' => $origin,
                'Referer' => $origin.'/login',
                'Accept' => 'application/json',
            ])->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

            $this->assertNotEquals(419, $response->getStatusCode(), 'API login must not return HTTP 419 CSRF mismatch');
            $response->assertOk();
        }
    }

    /**
     * Requirement 7: CSRF protection has not been broadly disabled for routes that genuinely require it.
     */
    public function test_csrf_protection_has_not_been_broadly_disabled_for_routes_that_genuinely_require_it(): void
    {
        // 1. Verify ValidateCsrfToken middleware does not contain broad wildcard exemptions
        $middleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            public function getNeverVerifyList(): array
            {
                return static::$neverVerify;
            }
        };

        $this->assertNotContains('*', $middleware->getExcludedPaths(), 'CSRF must not have a wildcard exemption');
        $this->assertNotContains('*', $middleware->getNeverVerifyList(), 'CSRF neverVerify must not contain wildcard');

        // 2. Direct invocation of ValidateCsrfToken without runningUnitTests bypass throws TokenMismatchException
        $activeCsrfMiddleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $session = $this->app['session']->driver();
        $session->start();

        $request = \Illuminate\Http\Request::create('/test-stateful-post', 'POST');
        $request->setLaravelSession($session);

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $activeCsrfMiddleware->handle($request, fn () => response()->json(['success' => true]));
    }

    /**
     * Requirement 8: Existing role and permission checks remain enforced.
     */
    public function test_existing_role_and_permission_checks_remain_enforced(): void
    {
        // 1. Member without required role cannot access academy routes (requires volunteer|mentor|admin|super-admin)
        $member = $this->createUser('member_only@sfu.ca', 'member');
        $memberToken = $member->createToken('member_token')->plainTextToken;

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => "Bearer {$memberToken}",
        ])->getJson('/api/v1/academy/courses')
          ->assertForbidden();

        // 2. User with admin role can access academy routes
        Role::firstOrCreate(['name' => 'Admin', 'slug' => 'admin']);
        $admin = $this->createUser('admin_user@sfu.ca', 'admin');
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $this->resetClient();
        $this->withHeaders([
            'Origin' => self::MAIN_ORIGIN,
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson('/api/v1/academy/courses')
          ->assertOk();
    }
}
