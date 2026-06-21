<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Verify Tester',
            'email' => 'tester@sfu.ca',
            'password' => bcrypt('password123'),
            'email_verified_at' => null,
        ]);
    }

    public function test_user_can_verify_email_with_valid_token(): void
    {
        // Setup token
        DB::table('email_verifications')->insert([
            'email' => 'tester@sfu.ca',
            'token' => hash('sha256', 'verification_token_123'),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson(route('api.auth.verify-email'), [
            'token' => 'verification_token_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Email verified successfully.',
            ]);

        $this->assertNotNull($this->user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verifications', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_user_cannot_verify_email_with_invalid_token(): void
    {
        DB::table('email_verifications')->insert([
            'email' => 'tester@sfu.ca',
            'token' => hash('sha256', 'verification_token_123'),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson(route('api.auth.verify-email'), [
            'token' => 'invalid_token_xyz',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);

        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);
        $this->user->assignRole('volunteer');

        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.auth.resend-verification'));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Verification email resent.',
            ]);

        $this->assertDatabaseHas('email_verifications', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_staff_user_cannot_resend_verification(): void
    {
        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->user->assignRole('admin');

        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.auth.resend-verification'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_already_verified_user_cannot_resend_verification(): void
    {
        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);
        $this->user->assignRole('volunteer');

        $this->user->email_verified_at = now();
        $this->user->save();

        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.auth.resend-verification'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
