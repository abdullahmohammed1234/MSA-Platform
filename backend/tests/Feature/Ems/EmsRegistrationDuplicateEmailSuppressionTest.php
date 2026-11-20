<?php

namespace Tests\Feature\Ems;

use App\Ems\Events\RegistrationCreated;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmsRegistrationDuplicateEmailSuppressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registration_created_suppresses_platform_mail_when_ems_notifications_enabled(): void
    {
        config(['ems.notifications.enabled' => true]);
        Notification::fake();

        $user = User::factory()->create();
        $event = Event::factory()->create(['name' => 'QR Confirmation Event']);
        $registration = $this->makeRegistration($event, $user);

        event(new RegistrationCreated($registration, $user));

        Notification::assertSentTo($user, PlatformNotification::class, function (PlatformNotification $notification, array $channels) {
            $this->assertNotContains('mail', $channels);
            $this->assertContains(\App\Services\Notifications\CustomDatabaseChannel::class, $channels);

            return true;
        });
    }

    #[Test]
    public function registration_created_keeps_platform_mail_when_ems_notifications_disabled(): void
    {
        config(['ems.notifications.enabled' => false]);
        Notification::fake();

        $user = User::factory()->create();
        $event = Event::factory()->create(['name' => 'Fallback Confirmation Event']);
        $registration = $this->makeRegistration($event, $user);

        event(new RegistrationCreated($registration, $user));

        Notification::assertSentTo($user, PlatformNotification::class, function (PlatformNotification $notification, array $channels) {
            $this->assertContains('mail', $channels);

            return true;
        });
    }

    #[Test]
    public function other_platform_notifications_still_include_mail_channel(): void
    {
        config(['ems.notifications.enabled' => true]);
        Notification::fake();

        $user = User::factory()->create();
        $user->notify(new PlatformNotification(
            'Store Order Update (#ORD-1)',
            'Your order was updated.',
            ['type' => 'event']
        ));

        Notification::assertSentTo($user, PlatformNotification::class, function (PlatformNotification $notification, array $channels) {
            $this->assertContains('mail', $channels);

            return true;
        });
    }

    #[Test]
    public function registration_created_still_persists_in_app_notification(): void
    {
        config(['ems.notifications.enabled' => true]);

        $user = User::factory()->create();
        $event = Event::factory()->create(['name' => 'In-App Registration Event']);
        $registration = $this->makeRegistration($event, $user);

        event(new RegistrationCreated($registration, $user));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Registration Confirmed: In-App Registration Event',
        ]);

        $row = NotificationModel::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($row);
    }

    protected function makeRegistration(Event $event, User $user): Registration
    {
        $registration = new Registration();
        $registration->uuid = (string) Str::uuid();
        $registration->event_id = $event->id;
        $registration->user_id = $user->id;
        $registration->reference = 'REG-SUPPRESS-' . Str::upper(Str::random(6));
        $registration->attendee_name = $user->name;
        $registration->attendee_email = $user->email;
        $registration->type = RegistrationType::Free;
        $registration->status = RegistrationStatus::Confirmed;
        $registration->save();

        return $registration;
    }
}
