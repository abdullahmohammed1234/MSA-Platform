<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_known_and_unknown_emails_receive_the_same_reset_response(): void
    {
        Notification::fake();

        $known = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'tester@sfu.ca',
        ]);

        $unknown = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'nonexistent@sfu.ca',
        ]);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
        $known->assertExactJson([
            'message' => PasswordResetService::GENERIC_RESET_REQUEST_MESSAGE,
        ]);

        $encoded = $known->getContent().$unknown->getContent();
        $this->assertStringNotContainsString('could not find', strtolower($encoded));
        $this->assertStringNotContainsString('does not exist', strtolower($encoded));
        $this->assertStringNotContainsString('token', strtolower($encoded));

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent@sfu.ca',
        ]);

        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
        Notification::assertCount(1);
    }

    public function test_reset_request_matches_email_case_without_changing_the_response(): void
    {
        Notification::fake();

        $response = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'Tester@SFU.ca',
        ]);

        $response->assertOk()->assertExactJson([
            'message' => PasswordResetService::GENERIC_RESET_REQUEST_MESSAGE,
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    }

    public function test_malformed_email_is_rejected_without_an_existence_error(): void
    {
        Notification::fake();

        $missing = $this->postJson(route('api.auth.forgot-password'), []);
        $invalid = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'not-an-email',
        ]);

        $missing->assertStatus(422)->assertJsonValidationErrors(['email']);
        $invalid->assertStatus(422)->assertJsonValidationErrors(['email']);

        $body = strtolower($missing->getContent().$invalid->getContent());
        $this->assertStringNotContainsString('could not find', $body);
        $this->assertStringNotContainsString('does not exist', $body);
        $this->assertStringNotContainsString('selected email is invalid', $body);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_forgot_password_is_rate_limited_without_sending_unlimited_mail(): void
    {
        Notification::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('api.auth.forgot-password'), [
                'email' => 'nonexistent@sfu.ca',
            ])->assertOk()->assertExactJson([
                'message' => PasswordResetService::GENERIC_RESET_REQUEST_MESSAGE,
            ]);
        }

        $limited = $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'tester@sfu.ca',
        ]);

        $limited->assertStatus(429);
        $this->assertStringNotContainsString('tester@sfu.ca', strtolower($limited->getContent()));
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_mail_failure_still_returns_the_generic_response(): void
    {
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp unavailable'));
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.45'])
            ->postJson(route('api.auth.forgot-password'), [
                'email' => 'tester@sfu.ca',
            ]);

        $response->assertOk()->assertExactJson([
            'message' => PasswordResetService::GENERIC_RESET_REQUEST_MESSAGE,
        ]);
        $this->assertStringNotContainsString('smtp', strtolower($response->getContent()));
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
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
        $this->assertStringNotContainsString($token, $response->getContent());

        $this->assertTrue(Hash::check('NewSecurePassword123!', $this->user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
    }

    public function test_user_cannot_reset_password_with_invalid_token(): void
    {
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
        $this->assertStringNotContainsString('could not find', strtolower($response->getContent()));
    }

    public function test_unknown_email_reset_does_not_reveal_that_the_account_is_missing(): void
    {
        $response = $this->postJson(route('api.auth.reset-password'), [
            'email' => 'nonexistent@sfu.ca',
            'token' => Str::random(64),
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['token']);
        $response->assertJsonMissingValidationErrors(['email']);
        $body = strtolower($response->getContent());
        $this->assertStringNotContainsString('could not find', $body);
        $this->assertStringNotContainsString('does not exist', $body);
    }

    public function test_expired_token_is_rejected_and_removed(): void
    {
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'tester@sfu.ca',
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->postJson(route('api.auth.reset-password'), [
            'email' => 'tester@sfu.ca',
            'token' => $token,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->assertStringContainsString('expired', strtolower($response->getContent()));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'tester@sfu.ca',
        ]);
        $this->assertTrue(Hash::check('oldpassword', $this->user->fresh()->password));
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'tester@sfu.ca',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'tester@sfu.ca',
            'token' => $token,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ];

        $this->postJson(route('api.auth.reset-password'), $payload)->assertOk();
        $second = $this->postJson(route('api.auth.reset-password'), $payload);

        $second->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->assertStringNotContainsString($token, $second->getContent());
    }
}
