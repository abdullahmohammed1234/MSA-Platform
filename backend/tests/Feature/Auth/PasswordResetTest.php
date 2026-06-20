<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Reset Tester',
            'email' => 'tester@sfu.ca',
            'password' => Hash::make('oldpassword'),
        ]);
    }

    public function test_user_can_request_password_reset_link(): void
    {
        $response = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'tester@sfu.ca',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Check your email for reset instructions.',
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_user_cannot_request_reset_link_for_non_existent_email(): void
    {
        $response = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'nonexistent@sfu.ca',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        // Setup token
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'tester@sfu.ca',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('api.auth.reset-password'), [
            'email' => 'tester@sfu.ca',
            'token' => $token,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Password reset successful. You can now login.',
            ]);

        // Check password changed
        $this->assertTrue(Hash::check('NewSecurePassword123!', $this->user->fresh()->password));

        // Check token deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_user_cannot_reset_password_with_invalid_token(): void
    {
        // Setup token
        DB::table('password_reset_tokens')->insert([
            'email' => 'tester@sfu.ca',
            'token' => Hash::make('correct_token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('api.auth.reset-password'), [
            'email' => 'tester@sfu.ca',
            'token' => 'incorrect_token',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}
