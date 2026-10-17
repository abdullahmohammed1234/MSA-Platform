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
     * @return array{cookie: string, token: string}
     */
    private function browserLogin(User $user): array
    {
        $this->resetClient();
        $this->withHeaders(['Origin' => $this->origin, 'Referer' => $this->origin.'/'])
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->resetClient();
        $response = $this->withHeaders(['Origin' => $this->origin, 'Referer' => $this->origin.'/login'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertOk();

        $cookie = $response->getCookie(config('session.cookie'), true)?->getValue();
        $token = $response->json('data.token') ?? $response->json('token');
        $this->assertIsString($cookie);
        $this->assertIsString($token);

        return ['cookie' => $cookie, 'token' => $token];
    }

    private function browserCall(string $method, string $uri, ?string $cookie, ?string $token): TestResponse
    {
        $this->resetClient();
        $headers = ['Origin' => $this->origin, 'Referer' => $this->origin.'/'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        $request = $this->withCredentials()->withHeaders($headers);
        if ($cookie !== null) {
            $request = $request->withCookie(config('session.cookie'), $cookie);
        }

        return $request->json($method, $uri);
    }

    private function bearerCall(string $method, string $uri, string $token): TestResponse
    {
        $this->resetClient();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token])->json($method, $uri);
    }

    #[DataProvider('clients')]
    public function test_browser_logout_revokes_the_token_and_the_session(string $origin): void
    {
        $this->origin = $origin;
        $user = $this->user();
        $device = $this->browserLogin($user);

        $this->bearerCall('GET', self::PROTECTED, $device['token'])->assertOk();
        $this->browserCall('GET', self::PROTECTED, $device['cookie'], null)->assertOk();

        $this->browserCall('POST', '/api/v1/auth/logout', $device['cookie'], $device['token'])->assertOk();

        $this->bearerCall('GET', self::PROTECTED, $device['token'])->assertUnauthorized();
        $this->browserCall('GET', self::PROTECTED, $device['cookie'], null)->assertUnauthorized();
        $this->browserCall('GET', self::PROTECTED, $device['cookie'], $device['token'])->assertUnauthorized();
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

        $this->browserCall('POST', '/api/v1/auth/logout', $laptop['cookie'], $laptop['token'])->assertOk();

        $this->bearerCall('GET', self::PROTECTED, $laptop['token'])->assertUnauthorized();
        $this->bearerCall('GET', self::PROTECTED, $phone['token'])->assertOk();
        $this->browserCall('GET', self::PROTECTED, $phone['cookie'], null)->assertOk();
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

    public function test_session_only_logout_ends_the_session_without_touching_tokens(): void
    {
        $user = $this->user();
        $device = $this->browserLogin($user);

        $this->browserCall('POST', '/api/v1/auth/logout', $device['cookie'], null)->assertOk();

        $this->browserCall('GET', self::PROTECTED, $device['cookie'], null)->assertUnauthorized();
        $this->bearerCall('GET', self::PROTECTED, $device['token'])->assertOk();
    }

    public function test_logout_with_an_already_revoked_token_is_rejected_not_reported_as_success(): void
    {
        $user = $this->user();
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->bearerCall('POST', '/api/v1/auth/logout', $token)->assertOk();

        $this->bearerCall('POST', '/api/v1/auth/logout', $token)->assertUnauthorized();
    }
}
