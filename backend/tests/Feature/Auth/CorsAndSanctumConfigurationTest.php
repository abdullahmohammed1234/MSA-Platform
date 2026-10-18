<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class CorsAndSanctumConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private const ENV_KEYS = ['APP_ENV', 'FRONTEND_URL', 'CONNECT_URL', 'CORS_ALLOWED_ORIGINS'];

    /** @var array<string, array{0: mixed, 1: mixed, 2: string|false}> */
    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => [$server, $env, $put]) {
            $this->restoreEnv($key, $server, $env, $put);
        }
        $this->savedEnv = [];

        parent::tearDown();
    }

    /**
     * @param array<string, string|null> $values
     * @return array<string, mixed>
     */
    private function reloadCorsConfigWith(array $values): array
    {
        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] ??= [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
            $value = array_key_exists($key, $values) ? $values[$key] : ($this->savedEnv[$key][0] ?? null);
            $this->restoreEnv($key, $value, $value, $value ?? false);
        }

        $cors = require config_path('cors.php');
        config(['cors' => $cors]);

        return $cors;
    }

    private function restoreEnv(string $key, mixed $server, mixed $env, string|false $put): void
    {
        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }
        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }
        putenv($put === false ? $key : "{$key}={$put}");
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

    private function createMemberUser(string $email = 'cors_member@example.com'): User
    {
        Role::firstOrCreate(['name' => 'Member', 'slug' => 'member']);

        $user = User::create([
            'name' => 'CORS Test User',
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync(Role::where('slug', 'member')->pluck('id'));

        return $user;
    }

    public function test_cors_allows_explicit_local_origins_with_credentials_and_not_a_wildcard(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertNotContains('*', $origins);
        $this->assertContains('http://localhost:5173', $origins);
        $this->assertContains('http://localhost:5174', $origins);
        $this->assertContains('http://127.0.0.1:5173', $origins);
        $this->assertContains('http://127.0.0.1:5174', $origins);
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], config('cors.paths'));
    }

    public function test_connect_origin_is_read_from_connect_url_for_cors_and_sanctum(): void
    {
        $cors = file_get_contents(config_path('cors.php'));
        $sanctum = file_get_contents(config_path('sanctum.php'));
        $example = file_get_contents(base_path('.env.example'));

        $this->assertIsString($cors);
        $this->assertIsString($sanctum);
        $this->assertIsString($example);

        $this->assertStringContainsString("env('CONNECT_URL'", $cors);
        $this->assertStringContainsString("env('CONNECT_URL'", $sanctum);
        $this->assertStringContainsString('CONNECT_URL=http://localhost:5174', $example);
        $this->assertStringContainsString('https://connect.sfumsa.ca', $example);
        $this->assertStringContainsString('connect.sfumsa.ca', $example);

        $stateful = config('sanctum.stateful');
        $this->assertContains('localhost:5174', $stateful);
        $this->assertContains('127.0.0.1:5174', $stateful);
        $this->assertNotContains('*', $stateful);
    }

    public function test_session_cookie_defaults_are_host_only_httponly_and_lax(): void
    {
        $session = file_get_contents(config_path('session.php'));
        $this->assertIsString($session);
        $this->assertStringContainsString("env('SESSION_DOMAIN')", $session);
        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE')", $session);
        $this->assertStringContainsString("env('SESSION_HTTP_ONLY', true)", $session);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $session);
        $this->assertStringContainsString("env('SESSION_LIFETIME', 120)", $session);
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) config('session.http_only'));
    }

    public function test_v1_release_scope_is_a_subset_and_does_not_remove_other_systems(): void
    {
        $ids = array_keys(config('systems.applications', []));
        sort($ids);

        $this->assertSame([
            'cms',
            'connect',
            'dams',
            'dawah-academy',
            'donations',
            'ems',
            'main-website',
            'mlibms',
            'sponsorship',
            'store',
            'vms',
        ], $ids);

        $scope = config('systems.v1_release_scope');
        sort($scope);
        $this->assertSame(['cms', 'connect', 'ems', 'main-website'], $scope);

        foreach (['dams', 'dawah-academy', 'donations', 'mlibms', 'sponsorship', 'store', 'vms'] as $kept) {
            $this->assertArrayHasKey($kept, config('systems.applications'));
        }
    }

    public function test_connect_transactional_mail_links_use_the_connect_origin(): void
    {
        config([
            'app.connect_url' => 'https://connect.example.test',
            'app.frontend_url' => 'https://www.example.test',
        ]);

        $sender = new \App\Models\User(['name' => 'Staff Member']);
        $recipient = new \App\Models\User(['name' => 'Member']);
        $conversation = new \App\Models\Conversation(['uuid' => 'conv-123']);
        $inquiry = new \App\Models\SupportInquiry([
            'reference_code' => 'INQ-202610-0001',
            'subject' => 'Prayer space',
        ]);

        $direct = (new \App\Notifications\DirectMessageNotification($conversation, $sender))->toMail($recipient);
        $reply = (new \App\Notifications\SupportReplyNotification($inquiry, new \App\Models\SupportMessage()))->toMail($recipient);
        $status = (new \App\Notifications\SupportStatusUpdatedNotification($inquiry, 'open', 'resolved'))->toMail($recipient);

        $this->assertSame('https://connect.example.test/app/messages/conv-123', $direct->actionUrl);
        $this->assertSame('https://connect.example.test/app/support/INQ-202610-0001', $reply->actionUrl);
        $this->assertSame('https://connect.example.test/app/support/INQ-202610-0001', $status->actionUrl);
        $this->assertStringNotContainsString('www.example.test', (string) $direct->actionUrl);
    }

    public function test_cors_preflight_allows_main_frontend_origin(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->call(
            'OPTIONS',
            '/api/v1/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://sfumsa.ca',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,x-requested-with',
            ]
        );

        $this->assertTrue(in_array($response->getStatusCode(), [200, 204], true));
        $this->assertSame('https://sfumsa.ca', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('GET', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function test_cors_preflight_allows_connect_origin_with_token_and_xsrf_headers(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->call(
            'OPTIONS',
            '/api/v1/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://connect.sfumsa.ca',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,x-requested-with,x-xsrf-token',
            ]
        );

        $this->assertTrue(in_array($response->getStatusCode(), [200, 204], true));
        $this->assertSame('https://connect.sfumsa.ca', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_cors_preflight_allows_authorization_and_standard_headers(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->call(
            'OPTIONS',
            '/api/v1/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://sfumsa.ca',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,x-requested-with,accept,origin,x-xsrf-token',
            ]
        );

        $this->assertTrue(in_array($response->getStatusCode(), [200, 204], true));
        $this->assertSame('https://sfumsa.ca', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $allowHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowHeaders);
        $this->assertStringContainsString('content-type', $allowHeaders);
    }

    public function test_cors_preflight_rejects_disallowed_origin(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->call(
            'OPTIONS',
            '/api/v1/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://malicious.attacker.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
            ]
        );

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_actual_request_omits_allow_origin_for_untrusted_origin(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->withHeaders([
            'Origin' => 'https://unauthorized-domain.com',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_actual_request_includes_allow_origin_for_trusted_origins(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $response = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertSame('https://sfumsa.ca', $response->headers->get('Access-Control-Allow-Origin'));

        $connectResponse = $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertSame('https://connect.sfumsa.ca', $connectResponse->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_allows_localhost_in_development_environment(): void
    {
        $this->reloadCorsConfigWith([
            'APP_ENV' => 'local',
            'FRONTEND_URL' => 'http://localhost:5173',
            'CONNECT_URL' => 'http://localhost:5174',
            'CORS_ALLOWED_ORIGINS' => '',
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_cors_excludes_localhost_in_production_environment(): void
    {
        $this->reloadCorsConfigWith([
            'APP_ENV' => 'production',
            'FRONTEND_URL' => 'https://sfumsa.ca',
            'CONNECT_URL' => 'https://connect.sfumsa.ca',
            'CORS_ALLOWED_ORIGINS' => '',
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));

        // Production origin remains explicitly allowed
        $prodResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertSame('https://sfumsa.ca', $prodResponse->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $prodResponse->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_cors_production_strictly_forbids_wildcard_origin(): void
    {
        $cors = $this->reloadCorsConfigWith([
            'APP_ENV' => 'production',
            'FRONTEND_URL' => 'https://sfumsa.ca',
            'CONNECT_URL' => 'https://connect.sfumsa.ca',
            'CORS_ALLOWED_ORIGINS' => '*',
        ]);

        $this->assertNotContains('*', $cors['allowed_origins']);
        $this->assertSame([], $cors['allowed_origins_patterns']);

        $response = $this->withHeaders([
            'Origin' => 'https://random-attacker.org',
        ])->getJson('/api/v1/ems/public/events');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_bearer_token_authenticates_from_main_and_connect_origins(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);
        $user = $this->createMemberUser('bearer_origins@example.com');
        $token = $user->createToken('auth_token')->plainTextToken;

        // 1. From main website origin
        $mainResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $mainResponse->assertOk()
            ->assertJsonPath('user.email', 'bearer_origins@example.com');
        $this->assertSame('https://sfumsa.ca', $mainResponse->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $mainResponse->headers->get('Access-Control-Allow-Credentials'));

        // 2. From Connect origin
        $connectResponse = $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $connectResponse->assertOk()
            ->assertJsonPath('user.email', 'bearer_origins@example.com');
        $this->assertSame('https://connect.sfumsa.ca', $connectResponse->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $connectResponse->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_unauthenticated_requests_remain_unauthorized_across_origins(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        // Main site without credentials
        $mainResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->getJson('/api/v1/auth/me');

        $mainResponse->assertUnauthorized();
        $this->assertSame('https://sfumsa.ca', $mainResponse->headers->get('Access-Control-Allow-Origin'));

        // Connect without credentials
        $connectResponse = $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
        ])->getJson('/api/v1/auth/me');

        $connectResponse->assertUnauthorized();
        $this->assertSame('https://connect.sfumsa.ca', $connectResponse->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_invalid_expired_and_revoked_tokens_are_rejected(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);
        $user = $this->createMemberUser('token_validations@example.com');

        // 1. Invalid token string
        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer invalid-token-string-99999',
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();

        // 2. Revoked token
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->tokens()->delete();

        $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();

        // 3. Expired token (expires_at in the past)
        $expiredTokenInstance = $user->tokens()->create([
            'name' => 'auth_token',
            'token' => hash('sha256', $plainText = 'test_expired_token_secret'),
            'abilities' => ['*'],
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer '.$expiredTokenInstance->id.'|'.$plainText,
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_existing_login_logout_and_password_reset_behavior_remains_unchanged(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);
        $user = $this->createMemberUser('lifecycle_user@example.com');

        // 1. Login from main frontend
        $this->resetClient();
        $loginResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'lifecycle_user@example.com',
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
        $token = $loginResponse->json('token');
        $this->assertIsString($token);

        // 2. Authorized request with token from Connect origin
        $this->resetClient();
        $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me')->assertOk();

        // 3. Logout from Connect origin with token
        $this->resetClient();
        $logoutResponse = $this->withHeaders([
            'Origin' => 'https://connect.sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        // Token must now be revoked
        $this->resetClient();
        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();

        // 4. Password reset token revocation
        $secondToken = $user->createToken('auth_token')->plainTextToken;
        $this->resetClient();
        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer '.$secondToken,
        ])->getJson('/api/v1/auth/me')->assertOk();

        // Request reset link (generic response)
        $this->resetClient();
        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->postJson('/api/v1/auth/forgot-password', [
            'email' => 'lifecycle_user@example.com',
        ])->assertOk();

        // Set known reset token hash
        $resetToken = 'sample_password_reset_token_64chars_long_string_sample_for_testing';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'lifecycle_user@example.com'],
            ['token' => Hash::make($resetToken), 'created_at' => now()]
        );

        $this->resetClient();
        $resetResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
        ])->postJson('/api/v1/auth/reset-password', [
            'email' => 'lifecycle_user@example.com',
            'token' => $resetToken,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $resetResponse->assertOk();

        // Previous token must now be revoked after password reset
        $this->resetClient();
        $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Authorization' => 'Bearer '.$secondToken,
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_connect_preflight_for_reported_failing_endpoints_succeeds(): void
    {
        config(['cors.allowed_origins' => ['https://sfumsa.ca', 'https://connect.sfumsa.ca']]);

        $endpoints = [
            ['GET', '/api/v1/auth/me'],
            ['GET', '/api/v1/website/announcements'],
            ['POST', '/api/v1/auth/login'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->call(
                'OPTIONS',
                $uri,
                [],
                [],
                [],
                [
                    'HTTP_ORIGIN' => 'https://connect.sfumsa.ca',
                    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
                    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,x-requested-with,x-xsrf-token',
                ]
            );

            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 204], true),
                "Preflight for {$uri} failed with status {$response->getStatusCode()}"
            );
            $this->assertSame(
                'https://connect.sfumsa.ca',
                $response->headers->get('Access-Control-Allow-Origin'),
                "Preflight for {$uri} missing Access-Control-Allow-Origin"
            );
            $this->assertSame(
                'true',
                $response->headers->get('Access-Control-Allow-Credentials'),
                "Preflight for {$uri} missing Access-Control-Allow-Credentials"
            );
        }
    }

    public function test_csrf_cookie_bootstrap_allows_stateful_post_without_419(): void
    {
        $user = User::factory()->create([
            'email' => 'csrf_verify_'.uniqid().'@sfu.ca',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $this->resetClient();

        // 1. Fetch CSRF cookie on API origin
        $csrfResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Referer' => 'https://sfumsa.ca/',
        ])->get('/sanctum/csrf-cookie');

        $csrfResponse->assertNoContent();

        // 2. Extract XSRF token and session cookies
        $xsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN');
        $sessionCookie = $csrfResponse->getCookie(config('session.cookie'));

        $this->assertNotNull($xsrfCookie, 'XSRF-TOKEN cookie must be set');

        // 3. Post to login with decrypted X-XSRF-TOKEN and credentials
        $this->resetClient();
        $loginResponse = $this->withHeaders([
            'Origin' => 'https://sfumsa.ca',
            'Referer' => 'https://sfumsa.ca/login',
            'X-XSRF-TOKEN' => $xsrfCookie->getValue(),
        ])
        ->withCookie('XSRF-TOKEN', $xsrfCookie->getValue())
        ->withCookie(config('session.cookie'), $sessionCookie?->getValue())
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $this->assertNotSame(419, $loginResponse->getStatusCode(), 'Login must not return 419 CSRF mismatch when CSRF cookie is bootstrapped');
        $loginResponse->assertOk();
    }
}

