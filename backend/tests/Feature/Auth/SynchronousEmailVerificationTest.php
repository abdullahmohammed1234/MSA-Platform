<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SynchronousEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected EmailVerificationService $verificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verificationService = new EmailVerificationService();
    }

    public function test_send_verification_triggers_synchronous_notification_and_persists_hashed_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->verificationService->sendVerification($user);

        // Verify token in DB
        $verificationRow = DB::table('email_verifications')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($verificationRow);
        $this->assertNotNull($verificationRow->token);

        // Verify notification sent via notifyNow
        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class
        );
    }

    public function test_token_can_be_consumed_and_verifies_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->verificationService->sendVerification($user);

        // Retrieve raw token from notification
        $capturedToken = null;
        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification) use (&$capturedToken) {
                $capturedToken = $notification->getToken();
                return true;
            }
        );

        $this->assertNotNull($capturedToken);

        $result = $this->verificationService->verify($capturedToken);
        $this->assertTrue($result);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Token row must be cleaned up
        $this->assertDatabaseMissing('email_verifications', [
            'email' => $user->email,
        ]);
    }

    public function test_invalid_or_expired_token_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->verificationService->verify('invalid-token-12345');
    }

    public function test_resend_fails_if_user_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->verificationService->resend($user);
    }
}
