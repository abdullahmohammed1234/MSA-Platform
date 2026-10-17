<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SingleSignOnSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Member', 'slug' => 'member']);

        $this->user = User::create([
            'name' => 'SSO Test User',
            'email' => 'ssomember@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->user->roles()->sync(Role::where('slug', 'member')->pluck('id'));
    }

    public function test_login_from_frontend_origin_initiates_web_session_and_sets_cookie(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson(route('api.auth.login'), [
            'email' => 'ssomember@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);

        $this->assertNotNull($response->json('token'));
        // Verify session cookie is set
        $cookies = $response->headers->getCookies();
        $hasSessionCookie = false;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'session')) {
                $hasSessionCookie = true;
                break;
            }
        }
        $this->assertTrue($hasSessionCookie, 'Session cookie must be present in response');
    }

    public function test_login_from_connect_origin_initiates_web_session_and_sets_cookie(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
        ])->postJson(route('api.auth.login'), [
            'email' => 'ssomember@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token']);

        $cookies = $response->headers->getCookies();
        $hasSessionCookie = false;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'session')) {
                $hasSessionCookie = true;
                break;
            }
        }
        $this->assertTrue($hasSessionCookie, 'Connect origin must receive session cookie');
    }

    public function test_session_cookie_allows_cross_app_me_profile_access(): void
    {
        // 1. Sign in from main frontend
        $loginResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson(route('api.auth.login'), [
            'email' => 'ssomember@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);

        // 2. Access /api/v1/auth/me from Connect (port 5174) with the session cookie but WITHOUT Bearer token
        $meResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
        ])->getJson(route('api.auth.me'));

        $meResponse->assertStatus(200)
            ->assertJsonPath('user.email', 'ssomember@example.com');
    }

    public function test_logout_from_connect_invalidates_shared_session(): void
    {
        // 1. Sign in from main frontend
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson(route('api.auth.login'), [
            'email' => 'ssomember@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        // 2. Sign out from Connect
        $logoutResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
        ])->postJson(route('api.auth.logout'));

        $logoutResponse->assertStatus(200);

        // In PHPUnit, forget in-memory guard singletons to simulate a fresh HTTP request
        auth()->forgetGuards();

        // 3. Main frontend should now be unauthenticated
        $checkResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->getJson(route('api.auth.me'));

        $checkResponse->assertStatus(401);
    }

    public function test_bearer_token_still_works_statelessly_without_frontend_origin(): void
    {
        // Existing external client / mobile flow without web Origin headers
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.auth.me'));

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'ssomember@example.com');
    }

    public function test_sso_session_respects_authorization_boundaries(): void
    {
        // Standard member signs in
        $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
        ])->postJson(route('api.auth.login'), [
            'email' => 'ssomember@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        // Attempting to access an admin-only endpoint must still return 403 Forbidden
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
        ])->getJson('/api/v1/admin/users');

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]), 'Unauthorized admin access must be blocked');
    }
}
