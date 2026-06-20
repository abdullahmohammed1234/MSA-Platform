<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);
        Role::create(['name' => 'Member', 'slug' => 'member']);
    }

    public function test_volunteer_can_register_successfully(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Abdullah bin Muhammad',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'volunteer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'uuid',
                    'name',
                    'email',
                    'avatar',
                    'is_active',
                    'is_verified',
                    'roles',
                    'created_at',
                ],
            ]);

        $user = User::where('email', 'student@sfu.ca')->first();
        $this->assertTrue($user->hasRole('volunteer'));
        $this->assertFalse($user->hasRole('member'));
    }

    public function test_member_can_register_successfully(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Aisha Member',
            'email' => 'member@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'member',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'member@sfu.ca')->first();
        $this->assertTrue($user->hasRole('member'));
        $this->assertFalse($user->hasRole('volunteer'));
    }

    public function test_user_cannot_register_without_accepting_terms(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Abdullah bin Muhammad',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => false,
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['terms']);
    }

    public function test_user_cannot_register_with_mismatched_password(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Abdullah bin Muhammad',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword123!',
            'terms' => true,
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create([
            'email' => 'student@sfu.ca',
        ]);

        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Another User',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_with_non_sfu_email(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Student Example',
            'email' => 'student@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_without_role(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Student Example',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_user_cannot_register_with_invalid_role(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'Student Example',
            'email' => 'student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'mentor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
