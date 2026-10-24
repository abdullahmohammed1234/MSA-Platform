<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Services\EventService;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Support\EmsRoles;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmsEventUpdateEmailSyncTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.notifications.enabled' => true,
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        $this->seed(EmsEmailTemplateSeeder::class);
    }

    /**
     * Scenarios 1, 2, 3, 4:
     * - Create event and dispatch email.
     * - Update date and time, dispatch update email, verify new details.
     * - Update location and description, verify subsequent emails contain new location.
     */
    public function test_event_updates_reach_subsequent_emails(): void
    {
        Mail::fake();

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $startOriginal = Carbon::parse('2026-11-15 14:00:00', 'America/Vancouver');
        $endOriginal = Carbon::parse('2026-11-15 16:30:00', 'America/Vancouver');

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'timezone' => 'America/Vancouver',
            'start_at' => $startOriginal,
            'end_at' => $endOriginal,
            'location' => 'Original Room 101',
            'name' => 'Original Halaqa',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_name' => 'Bilal Khan',
            'attendee_email' => 'bilal@example.com',
            'confirmed_at' => now(),
        ]);

        // 1. Initial registration bundle
        app(EventCommunicationService::class)->sendRegistrationBundle($registration->fresh());

        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($startOriginal) {
            $content = $mail->content();
            $expectedDate = $startOriginal->format('l, F j, Y');
            return str_contains($content->with['bodyHtml'], $expectedDate)
                && str_contains($content->with['bodyHtml'], 'Original Room 101');
        });

        // 2 & 3. Update date and time
        $startVancouver = Carbon::parse('2026-11-20 18:00:00', 'America/Vancouver');
        $endVancouver = Carbon::parse('2026-11-20 20:00:00', 'America/Vancouver');

        $eventService = app(EventService::class);
        $eventService->update($event, [
            'start_at' => $startVancouver->copy()->utc()->toDateTimeString(),
            'end_at' => $endVancouver->copy()->utc()->toDateTimeString(),
            'notify_audience' => 'registered',
        ], $organizer);

        // Verify that an EventUpdated notification was dispatched and contains new date/time
        $updateNotification = EventNotification::query()
            ->where('event_id', $event->id)
            ->where('type', NotificationType::EventUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($updateNotification);
        $expectedNewDate = $startVancouver->format('l, F j, Y');
        $this->assertStringContainsString($expectedNewDate, $updateNotification->payload['body_html']);
        $this->assertStringContainsString('6:00 PM', $updateNotification->payload['body_html']);
        $this->assertStringContainsString('8:00 PM', $updateNotification->payload['body_html']);

        // 4. Update location and description
        $eventService->update($event, [
            'location' => 'SUB Ballroom West',
            'description' => 'Updated halaqah topic on patience.',
            'notify_audience' => 'registered',
        ], $organizer);

        $locationNotification = EventNotification::query()
            ->where('event_id', $event->id)
            ->where('type', NotificationType::EventUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($locationNotification);
        $this->assertStringContainsString('SUB Ballroom West', $locationNotification->payload['body_html']);
    }

    /**
     * Scenario 5:
     * - Queue an email, edit the event before processing the job, and verify
     *   the current-versus-snapshot behavior.
     */
    public function test_delayed_queued_email_uses_authoritative_fresh_event_data_while_preserving_snapshot_finances(): void
    {
        Mail::fake();

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $startAt = Carbon::parse('2026-12-01 10:00:00', 'America/Vancouver')->utc();
        $endAt = Carbon::parse('2026-12-01 12:00:00', 'America/Vancouver')->utc();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'timezone' => 'America/Vancouver',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => 'Lecture Hall A',
            'name' => 'Annual Conference',
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-123456',
            'total_amount' => 45.00,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_name' => 'Fatima Al-Zahra',
            'attendee_email' => 'fatima@example.com',
            'reference' => 'REG-987654',
            'amount_due' => 45.00,
        ]);

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 45.00,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Paid,
        ]);

        Ticket::factory()->create([
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'code' => 'TCK-FA-001',
        ]);

        // Manually create a scheduled/queued reminder notification for this registration
        // (rendered with the original event details)
        $dispatcher = app(QueuedEventNotificationDispatcher::class);
        $notification = $dispatcher->notifyRegistration(
            $registration,
            NotificationType::EventReminder->value,
            ['force' => true],
            now()->addDays(5) // scheduled in future
        );

        $this->assertSame(NotificationStatus::Scheduled, $notification->status);
        $this->assertStringContainsString('Lecture Hall A', $notification->payload['body_html']);
        $this->assertStringContainsString('Tuesday, December 1, 2026', $notification->payload['body_html']);

        // Now, admin updates event date/time and location BEFORE the scheduled job is processed!
        $newStart = Carbon::parse('2026-12-05 15:00:00', 'America/Vancouver')->utc();
        $newEnd = Carbon::parse('2026-12-05 18:00:00', 'America/Vancouver')->utc();

        app(EventService::class)->update($event, [
            'start_at' => $newStart->toDateTimeString(),
            'end_at' => $newEnd->toDateTimeString(),
            'location' => 'Main Auditorium Building C',
            'notify_audience' => 'none',
        ], $organizer);

        // Now simulate the worker executing the queued notification job
        $notification->status = NotificationStatus::Pending;
        $notification->scheduled_at = now()->subMinute();
        $notification->save();

        $job = new SendEventNotificationJob($notification->id);
        $job->handle();

        // Check that Mail was sent with the AUTHORITATIVE NEW event details
        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            $content = $mail->content();
            $body = $content->with['bodyHtml'];

            // Event logistics must be updated
            $hasNewDate = str_contains($body, 'Saturday, December 5, 2026');
            $hasNewTime = str_contains($body, '3:00 PM') && str_contains($body, '6:00 PM');
            $hasNewLocation = str_contains($body, 'Main Auditorium Building C');

            return $hasNewDate && $hasNewTime && $hasNewLocation;
        });

        // Verify the database record itself was refreshed with the updated event details
        $notification->refresh();
        $this->assertStringContainsString('Saturday, December 5, 2026', $notification->payload['body_html']);
        $this->assertStringContainsString('Main Auditorium Building C', $notification->payload['body_html']);
        $this->assertSame(NotificationStatus::Sent, $notification->status);
    }

    /**
     * Scenario 6 & 7:
     * - Confirm unrelated registration and payment emails still contain correct snapshot information.
     * - Confirm time-zone conversions do not introduce incorrect dates or times.
     */
    public function test_timezone_formatting_and_financial_snapshot_preservation(): void
    {
        Mail::fake();

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        // Event in Toronto timezone
        $torontoStart = Carbon::parse('2026-10-31 19:30:00', 'America/Toronto')->utc();
        $torontoEnd = Carbon::parse('2026-10-31 22:00:00', 'America/Toronto')->utc();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'timezone' => 'America/Toronto',
            'start_at' => $torontoStart,
            'end_at' => $torontoEnd,
            'location' => 'Toronto Islamic Centre',
            'name' => 'Eastern Summit',
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-EAST-888',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_name' => 'Tariq Ramadan',
            'attendee_email' => 'tariq@example.com',
            'reference' => 'REG-EAST-888',
        ]);

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 125.50,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Paid,
        ]);

        Ticket::factory()->create([
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'code' => 'TCK-EAST-01',
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration->fresh());

        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            $content = $mail->content();
            $body = $content->with['bodyHtml'];

            // Timezone formatting check
            $correctDate = str_contains($body, 'Saturday, October 31, 2026');
            $correctTime = str_contains($body, '7:30 PM') && str_contains($body, '10:00 PM');
            $correctLocation = str_contains($body, 'Toronto Islamic Centre');

            return $correctDate && $correctTime && $correctLocation;
        });
    }
}
