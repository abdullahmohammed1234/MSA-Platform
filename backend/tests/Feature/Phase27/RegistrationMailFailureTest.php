<?php

namespace Tests\Feature\Phase27;

use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Account registration commits the user before the verification email is sent
 * synchronously. A mail outage must not turn that committed registration into an error.
 */
class RegistrationMailFailureTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'outage.student@sfu.ca';

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);
        Role::create(['name' => 'Member', 'slug' => 'member']);
    }

    private function failMailTransport(): void
    {
        $this->app->bind(MailChannel::class, function () {
            $mock = Mockery::mock(MailChannel::class);
            $mock->shouldReceive('send')->andThrow(
                new \RuntimeException('Connection could not be established with host smtp.test: secret-pass-123')
            );

            return $mock;
        });
    }

    private function register(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('api.auth.register'), [
            'name' => 'Outage Student',
            'email' => self::EMAIL,
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'gender' => 'male',
            'terms' => true,
            'role' => 'volunteer',
        ]);
    }

    public function test_mail_outage_does_not_report_a_committed_registration_as_failed(): void
    {
        Log::spy();
        $this->failMailTransport();

        $response = $this->register();

        $response->assertCreated()
            ->assertJsonPath('verification_email_sent', false)
            ->assertJsonStructure(['message', 'user' => ['uuid', 'email'], 'token']);

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue($user->hasRole('volunteer'));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(1, DB::table('email_verifications')->where('email', self::EMAIL)->count());

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context = []) use ($user) {
            $encoded = json_encode($context);

            return str_contains($message, 'Verification email')
                && ($context['user_id'] ?? null) === $user->id
                && ! str_contains($encoded, self::EMAIL)
                && ! str_contains($encoded, 'secret-pass-123');
        })->once();
    }

    public function test_successful_send_reports_the_email_as_sent(): void
    {
        Notification::fake();

        $this->register()->assertCreated()->assertJsonPath('verification_email_sent', true);

        Notification::assertSentTo(User::where('email', self::EMAIL)->firstOrFail(), VerifyEmailNotification::class);
    }

    public function test_user_can_recover_with_resend_once_mail_is_back(): void
    {
        $this->failMailTransport();
        $this->register()->assertCreated();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $firstToken = DB::table('email_verifications')->where('email', self::EMAIL)->value('token');

        $this->app->offsetUnset(MailChannel::class);
        Notification::fake();
        Sanctum::actingAs($user);

        $this->postJson(route('api.auth.resend-verification'))->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $tokens = DB::table('email_verifications')->where('email', self::EMAIL)->pluck('token');
        $this->assertCount(1, $tokens);
        $this->assertNotSame($firstToken, $tokens->first());
    }

    public function test_retrying_registration_after_an_outage_is_rejected_as_duplicate_not_duplicated(): void
    {
        $this->failMailTransport();
        $this->register()->assertCreated();

        $this->register()->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }
}
