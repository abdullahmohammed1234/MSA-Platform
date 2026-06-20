<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Student', 'slug' => 'student']);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'student@sfu.ca',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'student@sfu.ca',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);

        $this->assertNotNull($response->json('token'));
        $this->assertNotNull($this->user->fresh()->last_login_at);
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'student@sfu.ca',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_login_if_deactivated(): void
    {
        $this->user->is_active = false;
        $this->user->save();

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'student@sfu.ca',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.auth.logout'));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Logout successful.',
            ]);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }

    public function test_authenticated_user_can_fetch_me_profile(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.auth.me'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'uuid',
                    'name',
                    'email',
                    'is_verified',
                    'roles',
                ],
            ]);
    }

    public function test_staff_can_login_with_non_sfu_email(): void
    {
        Role::create(['name' => 'Admin', 'slug' => 'admin']);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user']);
    }

    public function test_volunteer_cannot_login_with_non_sfu_email(): void
    {
        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);

        $volunteer = User::create([
            'name' => 'Volunteer User',
            'email' => 'volunteer@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $volunteer->roles()->sync(Role::where('slug', 'volunteer')->pluck('id'));

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'volunteer@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment([
                'email' => ['Members and volunteers must sign in with an @sfu.ca email address.'],
            ]);
    }
}
