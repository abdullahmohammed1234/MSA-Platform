<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Both SPAs send credentials and fetch /sanctum/csrf-cookie before login, so
 * login also creates a web session that Sanctum checks before the bearer
 * token. These tests prove that session cannot outlive the revocation rules.
 *
 * Sessions are persisted in the database and the session store is rebuilt
 * before every request, so each simulated device only sees its own session.
 */
class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'session'.uniqid().'@sfu.ca',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
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
    private function spaLogin(User $user, string $password = 'OldPassword123!'): array
    {
        $this->resetClient();

        $response = $this->withHeaders(['Referer' => self::SPA.'/login', 'Origin' => self::SPA])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])
            ->assertOk();

        $cookie = $response->getCookie(config('session.cookie'), true)?->getValue();
        $this->assertIsString($cookie, 'Stateful login did not issue a session cookie');

        $token = $response->json('data.token') ?? $response->json('token');
        $this->assertIsString($token);

        return ['cookie' => $cookie, 'token' => $token];
    }

    private function spaRequest(string $method, string $uri, ?string $cookie, ?string $token = null, array $data = []): TestResponse
    {
        $this->resetClient();

        $headers = ['Referer' => self::SPA.'/', 'Origin' => self::SPA];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $request = $this->withCredentials()->withHeaders($headers);
        if ($cookie !== null) {
            $request = $request->withCookie(config('session.cookie'), $cookie);
        }

        return $request->json($method, $uri, $data);
    }

    private function tokenRequest(string $method, string $uri, string $token, array $data = []): TestResponse
    {
        $this->resetClient();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token])->json($method, $uri, $data);
    }

    public function test_session_cookie_alone_authenticates_after_stateful_login(): void
    {
        $device = $this->spaLogin($this->makeUser());

        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])->assertOk();
    }

    public function test_request_without_session_cookie_is_not_authenticated(): void
    {
        $this->spaLogin($this->makeUser());

        $this->spaRequest('GET', '/api/v1/auth/me', null)->assertUnauthorized();
    }

    public function test_password_change_on_another_device_ends_this_session(): void
    {
        $user = $this->makeUser();
        $deviceA = $this->spaLogin($user);
        $this->spaRequest('GET', '/api/v1/auth/me', $deviceA['cookie'])->assertOk();

        $tokenB = $user->createToken('auth_token')->plainTextToken;
        $this->tokenRequest('PUT', '/api/v1/account/password', $tokenB, [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ])->assertOk();

        $this->spaRequest('GET', '/api/v1/auth/me', $deviceA['cookie'])->assertUnauthorized();
        $this->spaRequest('GET', '/api/v1/auth/me', $deviceA['cookie'], $deviceA['token'])->assertUnauthorized();
    }

    public function test_device_that_changed_the_password_keeps_its_session(): void
    {
        $user = $this->makeUser();
        $device = $this->spaLogin($user);

        $replacement = $this->spaRequest('PUT', '/api/v1/account/password', $device['cookie'], $device['token'], [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ])->assertOk()->json('token');

        $this->assertSame(1, $user->tokens()->count());
        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])->assertOk();
        $this->tokenRequest('GET', '/api/v1/auth/me', $device['token'])->assertUnauthorized();
        $this->tokenRequest('GET', '/api/v1/auth/me', $replacement)->assertOk();
    }

    public function test_password_reset_ends_existing_sessions(): void
    {
        $user = $this->makeUser();
        $device = $this->spaLogin($user);
        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])->assertOk();

        $user->forceFill(['password' => Hash::make('ResetPassword123!')])->save();

        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])->assertUnauthorized();
    }

    public function test_deactivated_user_session_is_rejected(): void
    {
        $user = $this->makeUser();
        $device = $this->spaLogin($user);

        $user->forceFill(['is_active' => false])->save();

        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Your account has been deactivated. Please contact support.');
    }

    public function test_spa_logout_revokes_the_session_and_the_presented_token(): void
    {
        $user = $this->makeUser();
        $device = $this->spaLogin($user);
        $other = $user->createToken('auth_token')->plainTextToken;

        $this->spaRequest('POST', '/api/v1/auth/logout', $device['cookie'], $device['token'])->assertOk();

        $this->spaRequest('GET', '/api/v1/auth/me', $device['cookie'])->assertUnauthorized();
        $this->tokenRequest('GET', '/api/v1/auth/me', $device['token'])->assertUnauthorized();
        $this->tokenRequest('GET', '/api/v1/auth/me', $other)->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_logout_does_not_revoke_another_users_presented_token(): void
    {
        $user = $this->makeUser();
        $victim = $this->makeUser();
        $device = $this->spaLogin($user);
        $victimToken = $victim->createToken('auth_token')->plainTextToken;

        $this->spaRequest('POST', '/api/v1/auth/logout', $device['cookie'], $victimToken)->assertOk();

        $this->tokenRequest('GET', '/api/v1/auth/me', $victimToken)->assertOk();
        $this->assertSame(1, $victim->tokens()->count());
    }
}
