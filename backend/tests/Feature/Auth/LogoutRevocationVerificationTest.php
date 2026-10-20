<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Logout verification for both browser clients, using their real origins and
 * the same sequence their auth stores use: CSRF cookie, login with
 * credentials, bearer token plus session cookie on every call, then
 * POST /auth/logout. Token values are only held in memory and never printed.
 */
class LogoutRevocationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PROTECTED = '/api/v1/notifications';

    private string $origin = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public static function clients(): array
    {
        return [
            'main frontend' => ['http://localhost:5173'],
            'connect' => ['http://localhost:5174'],
        ];
    }

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'logout'.uniqid().'@sfu.ca',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
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
     * @return array{token: string}
     */
    private function browserLogin(User $user): array
    {
        $this->resetClient();
        $response = $this->withHeaders(['Origin' => $this->origin, 'Referer' => $this->origin.'/login'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertOk();

        $token = $response->json('data.token') ?? $response->json('token');
        $this->assertIsString($token);

        return ['token' => $token];
    }

    private function bearerCall(string $method, string $uri, string $token): TestResponse
    {
        $this->resetClient();

        return $this->withHeaders([
            'Origin' => $this->origin,
            'Referer' => $this->origin.'/',
            'Authorization' => 'Bearer '.$token,
        ])->json($method, $uri);
    }

    #[DataProvider('clients')]
    public function test_browser_logout_revokes_the_token(string $origin): void
    {
        $this->origin = $origin;
        $user = $this->user();
        $device = $this->browserLogin($user);

        $this->bearerCall('GET', self::PROTECTED, $device['token'])->assertOk();

        $this->bearerCall('POST', '/api/v1/auth/logout', $device['token'])->assertOk();

        $this->bearerCall('GET', self::PROTECTED, $device['token'])->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    #[DataProvider('clients')]
    public function test_browser_logout_keeps_the_users_other_devices_and_other_users(string $origin): void
    {
        $this->origin = $origin;
        $user = $this->user();
        $stranger = $this->user();
        $laptop = $this->browserLogin($user);
        $phone = $this->browserLogin($user);
        $strangerToken = $stranger->createToken('auth_token')->plainTextToken;

        $this->bearerCall('POST', '/api/v1/auth/logout', $laptop['token'])->assertOk();

        $this->bearerCall('GET', self::PROTECTED, $laptop['token'])->assertUnauthorized();
        $this->bearerCall('GET', self::PROTECTED, $phone['token'])->assertOk();
        $this->bearerCall('GET', self::PROTECTED, $strangerToken)->assertOk();
    }

    public function test_bearer_only_logout_revokes_only_that_token(): void
    {
        $user = $this->user();
        $first = $user->createToken('auth_token')->plainTextToken;
        $second = $user->createToken('auth_token')->plainTextToken;

        $this->bearerCall('POST', '/api/v1/auth/logout', $first)->assertOk();

        $this->bearerCall('GET', self::PROTECTED, $first)->assertUnauthorized();
        $this->bearerCall('GET', self::PROTECTED, $second)->assertOk();
    }

    public function test_unauthenticated_logout_is_rejected(): void
    {
        $this->resetClient();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_logout_with_an_already_revoked_token_is_rejected_not_reported_as_success(): void
    {
        $user = $this->user();
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->bearerCall('POST', '/api/v1/auth/logout', $token)->assertOk();

        $this->bearerCall('POST', '/api/v1/auth/logout', $token)->assertUnauthorized();
    }
}
