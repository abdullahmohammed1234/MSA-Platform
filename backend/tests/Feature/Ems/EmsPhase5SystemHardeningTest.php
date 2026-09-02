<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\AttendeeImportStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\EventStatus;
use App\Ems\Models\AttendeeImport;
use App\Ems\Models\Event;
use App\Ems\Models\EventReminder;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Models\WebhookEvent;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\ReminderService;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\WaitlistService;
use App\Ems\Support\EmsRoles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmsPhase5SystemHardeningTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'ems.tickets.enabled' => true,
            'ems.tickets.qr_enabled' => true,
            'ems.notifications.enabled' => true,
            'ems.payments.square.webhook_signature_key' => 'webhook-secret',
            'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
        ]);
    }

    protected function liveEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Tech Summit',
            'slug' => 'tech-summit-' . Str::lower(Str::random(4)),
            'capacity' => 100,
            'status' => \App\Ems\Enums\EventStatus::Live,
            'waitlist_enabled' => true,
        ], $attributes));
    }

    protected function freeTicketType(Event $event): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 0,
            'quantity' => 200,
            'is_active' => true,
        ]);
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $url = 'https://example.test/api/v1/webhooks/square';
        $signature = base64_encode(hash_hmac('sha256', $url . $body, 'webhook-secret', true));

        return $this->postJson(
            '/api/v1/webhooks/square',
            $payload,
            ['X-Square-Hmacsha256-Signature' => $signature]
        );
    }

    /**
     * Test waitlist stale promotion loop is resolved.
     */
    public function test_waitlist_stale_loop_clean_up(): void
    {
        $event = $this->liveEvent(['capacity' => 0]);
        $ticketType = $this->freeTicketType($event);

        $waitlistService = app(WaitlistService::class);

        // Join waitlist
        $entry1 = $waitlistService->join($event, [
            'first_name' => 'Stale',
            'last_name' => 'User',
            'email' => 'stale@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);

        $entry2 = $waitlistService->join($event, [
            'first_name' => 'Active',
            'last_name' => 'User',
            'email' => 'active@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);

        // Cancel first waitlist registration directly to make it stale
        $entry1->registration->status = RegistrationStatus::Cancelled;
        $entry1->registration->save();

        // Release capacity so promotion can run
        $event->update(['capacity' => 10]);

        $promoted = $waitlistService->promoteAvailable($event);

        // Assert 1 registration was promoted (the second one)
        $this->assertEquals(1, $promoted);

        // Assert the stale entry status became Left
        $entry1->refresh();
        $this->assertEquals(WaitlistStatus::Left, $entry1->status);

        // Assert the active entry status became Promoted (Waitlist Status) -> Confirmed (Registration Status)
        $entry2->refresh();
        $this->assertEquals(WaitlistStatus::Promoted, $entry2->status);
        $this->assertEquals(RegistrationStatus::Confirmed, $entry2->registration->status);
    }

    /**
     * Test public cancellation marks waitlist entry as left.
     */
    public function test_cancellation_marks_associated_waitlist_entry_as_left(): void
    {
        $event = $this->liveEvent(['capacity' => 0]);
        $ticketType = $this->freeTicketType($event);

        $user = $this->emsUser(EmsRoles::ATTENDEE);

        $waitlistService = app(WaitlistService::class);
        $entry = $waitlistService->join($event, [
            'first_name' => 'Cancel',
            'last_name' => 'User',
            'email' => $user->email,
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ], $user);

        // Cancel registration
        $response = $this->actingAs($user)
            ->postJson("/api/v1/ems/public/registrations/{$entry->registration->uuid}/cancel");

        $response->assertStatus(200);

        // Assert entry status updated to Left
        $entry->refresh();
        $this->assertEquals(WaitlistStatus::Left, $entry->status);
    }

    /**
     * Test lock sequence correctness during abandonment cleanup.
     */
    public function test_canonical_lock_ordering_is_safe(): void
    {
        $event = $this->liveEvent();
        $ticketType = $this->freeTicketType($event);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment,
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'total_amount' => 0.0,
            'status' => \App\Ems\Enums\OrderStatus::Pending,
        ]);

        $registration->order_id = $order->id;
        $registration->save();

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 0.0,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Pending,
        ]);

        $fulfillment = app(PaymentFulfillmentService::class);
        $abandoned = $fulfillment->markAbandoned($payment, 'Stale checkout');

        $this->assertEquals(PaymentStatus::Abandoned, $abandoned->status);
        $this->assertEquals(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }

    /**
     * Test reminder large dataset chunking and idempotency behavior.
     */
    public function test_reminder_large_dataset_chunking(): void
    {
        $event = $this->liveEvent();
        $ticketType = $this->freeTicketType($event);

        // Seed registrations
        $regs = [];
        for ($i = 0; $i < 5; $i++) {
            $regs[] = Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'attendee_name' => "Attendee {$i}",
                'attendee_email' => "attendee{$i}@example.com",
                'status' => RegistrationStatus::Confirmed,
            ]);
        }

        $reminder = EventReminder::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'offset_value' => 1,
            'offset_unit' => \App\Ems\Enums\ReminderOffsetUnit::Days->value,
            'enabled' => true,
            'template_key' => 'reminder_default',
            'audience' => \App\Ems\Enums\ReminderAudience::Confirmed->value,
        ]);

        $service = app(ReminderService::class);
        $queuedCount = $service->dispatchReminder($reminder);

        $this->assertEquals(5, $queuedCount);

        // Running again should be idempotent (will return 0 because we disable it on execution)
        $queuedCount2 = $service->dispatchReminder($reminder);
        $this->assertEquals(0, $queuedCount2);
    }

    /**
     * Test analytics breakdown correctness.
     */
    public function test_analytics_member_breakdown_correctness(): void
    {
        $event = $this->liveEvent();
        $ticketType = $this->freeTicketType($event);

        $roleMember = Role::firstOrCreate(['slug' => 'member', 'name' => 'Member']);
        $roleStudent = Role::firstOrCreate(['slug' => 'student', 'name' => 'Student']);

        $user1 = User::factory()->create();
        $user1->roles()->attach($roleMember);

        $user2 = User::factory()->create();
        $user2->roles()->attach($roleStudent);

        // Confirm registrations
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'user_id' => $user1->id,
            'quantity' => 1,
            'status' => RegistrationStatus::Confirmed,
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'user_id' => $user2->id,
            'quantity' => 2,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // Guest registration
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'user_id' => null,
            'quantity' => 3,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $service = app(\App\Ems\Services\AnalyticsService::class);
        $payload = $service->getDashboardPayload($admin, ['event_uuid' => $event->uuid]);

        $counts = $payload['charts']['member_breakdown']['counts'];
        $this->assertEquals(1, $counts['members']);
        $this->assertEquals(2, $counts['students']);
        $this->assertEquals(3, $counts['guests']);
    }

    /**
     * Test PII is redacted from stored Square webhooks.
     */
    public function test_square_webhook_pii_redaction(): void
    {
        $payload = [
            'event_id' => 'evt_9988',
            'type' => 'payment.created',
            'data' => [
                'id' => 'evt_9988',
                'object' => [
                    'payment' => [
                        'id' => 'pay_1122',
                        'buyer_email_address' => 'buyer@example.com',
                        'buyer_phone_number' => '+1234567890',
                        'customer_id' => 'cust_4455',
                        'billing_address' => [
                            'address_line_1' => '123 Main St',
                            'postal_code' => 'V5K 1A1',
                        ],
                        'shipping_address' => [
                            'address_line_1' => '456 Shipping Ave',
                        ],
                        'card_details' => [
                            'card' => [
                                'cardholder_name' => 'John Doe',
                                'brand' => 'VISA',
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Simulate posting to webhook
        $response = $this->postWebhook($payload);
        $response->assertOk();

        $stored = WebhookEvent::query()->where('event_id', 'evt_9988')->firstOrFail();
        $storedPayload = $stored->payload;

        $this->assertArrayNotHasKey('merchant_id', $storedPayload);
        $this->assertEquals('[REDACTED]', data_get($storedPayload, 'data.object.payment.buyer_email_address'));
        $this->assertEquals('[REDACTED]', data_get($storedPayload, 'data.object.payment.buyer_phone_number'));
        $this->assertEquals('[REDACTED]', data_get($storedPayload, 'data.object.payment.customer_id'));
        $this->assertNull(data_get($storedPayload, 'data.object.payment.billing_address'));
        $this->assertNull(data_get($storedPayload, 'data.object.payment.shipping_address'));
        $this->assertNull(data_get($storedPayload, 'data.object.payment.card_details.card.cardholder_name'));
    }

    /**
     * Test Excel file deletion after import completion.
     */
    public function test_attendee_import_spreadsheet_is_deleted(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('attendees.xlsx', 100);
        $path = $file->store('imports', 'local');

        $event = $this->liveEvent();

        $import = AttendeeImport::create([
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Previewed,
            'original_filename' => 'attendees.xlsx',
            'summary' => [
                'stored_path' => $path,
                'valid' => 0,
                'valid_rows' => [],
            ],
            'column_mapping' => [],
        ]);

        $service = app(\App\Ems\Services\Operations\AttendeeImportService::class);
        $service->processImport($import);

        // Assert file is deleted from local disk
        Storage::disk('local')->assertMissing($path);
    }

    public function test_waitlist_promotion_with_new_lock_ordering(): void
    {
        $event = $this->liveEvent(['capacity' => 0]);
        $ticketType = $this->freeTicketType($event);

        $waitlistService = app(WaitlistService::class);

        $entry = $waitlistService->join($event, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);

        $event->update(['capacity' => 1]);

        $promoted = $waitlistService->promoteAvailable($event);

        $this->assertEquals(1, $promoted);
        $this->assertEquals(WaitlistStatus::Promoted, $entry->fresh()->status);
        $this->assertEquals(RegistrationStatus::Confirmed, $entry->registration->fresh()->status);
    }

    public function test_stale_preview_cleanup_lifecycle(): void
    {
        Storage::fake('local');
        $disk = config('ems.storage.disk', 'local');
        $event = $this->liveEvent();

        // 1. Create a stale preview (older than 24 hours) with a file
        $stalePath = UploadedFile::fake()->create('stale.xlsx', 50)->store('imports', $disk);
        $staleImport = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Previewed,
            'original_filename' => 'stale.xlsx',
            'summary' => ['stored_path' => $stalePath],
        ]);
        DB::table('ems_attendee_imports')->where('id', $staleImport->id)->update(['created_at' => now()->subHours(25)]);

        // 2. Create a fresh preview (newer than 24 hours) with a file
        $freshPath = UploadedFile::fake()->create('fresh.xlsx', 50)->store('imports', $disk);
        $freshImport = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Previewed,
            'original_filename' => 'fresh.xlsx',
            'summary' => ['stored_path' => $freshPath],
        ]);
        DB::table('ems_attendee_imports')->where('id', $freshImport->id)->update(['created_at' => now()->subHours(5)]);

        // 3. Create a stale active import (status Processing, older than 24 hours) with a file
        $activePath = UploadedFile::fake()->create('active.xlsx', 50)->store('imports', $disk);
        $activeImport = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Processing,
            'original_filename' => 'active.xlsx',
            'summary' => ['stored_path' => $activePath],
        ]);
        DB::table('ems_attendee_imports')->where('id', $activeImport->id)->update(['created_at' => now()->subHours(26)]);

        // 4. Create a stale completed import (status Completed, older than 24 hours)
        $completedImport = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Completed,
            'original_filename' => 'completed.xlsx',
            'summary' => ['stored_path' => 'already-deleted-on-completion.xlsx'],
        ]);
        DB::table('ems_attendee_imports')->where('id', $completedImport->id)->update(['created_at' => now()->subHours(27)]);

        // 5. Create a stale preview with a missing physical file (to ensure safety)
        $missingImport = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Previewed,
            'original_filename' => 'missing.xlsx',
            'summary' => ['stored_path' => 'nonexistent.xlsx'],
        ]);
        DB::table('ems_attendee_imports')->where('id', $missingImport->id)->update(['created_at' => now()->subHours(30)]);

        // Run the cleanup job
        $job = new \App\Ems\Jobs\CleanupStalePreviewsJob();
        $job->handle();

        // 6. Assertions
        // Stale preview: DB record soft-deleted, file deleted
        $this->assertSoftDeleted('ems_attendee_imports', ['id' => $staleImport->id]);
        Storage::disk($disk)->assertMissing($stalePath);

        // Fresh preview: remains active, file remains
        $this->assertDatabaseHas('ems_attendee_imports', [
            'id' => $freshImport->id,
            'status' => AttendeeImportStatus::Previewed->value,
            'deleted_at' => null,
        ]);
        Storage::disk($disk)->assertExists($freshPath);

        // Active/Processing import: remains active, file remains
        $this->assertDatabaseHas('ems_attendee_imports', [
            'id' => $activeImport->id,
            'status' => AttendeeImportStatus::Processing->value,
            'deleted_at' => null,
        ]);
        Storage::disk($disk)->assertExists($activePath);

        // Completed import: remains untouched
        $this->assertDatabaseHas('ems_attendee_imports', [
            'id' => $completedImport->id,
            'status' => AttendeeImportStatus::Completed->value,
            'deleted_at' => null,
        ]);

        // Missing physical file import: DB record is still soft-deleted successfully (handled safely)
        $this->assertSoftDeleted('ems_attendee_imports', ['id' => $missingImport->id]);

        // 7. Repeated run is safe and idempotent
        $job->handle();
        $this->assertSoftDeleted('ems_attendee_imports', ['id' => $staleImport->id]);
        $this->assertSoftDeleted('ems_attendee_imports', ['id' => $missingImport->id]);
    }

    public function test_cleanup_skips_concurrently_transitioned_import(): void
    {
        Storage::fake('local');
        $disk = config('ems.storage.disk', 'local');
        $event = $this->liveEvent();

        $path = UploadedFile::fake()->create('concurrent.xlsx', 50)->store('imports', $disk);
        $import = AttendeeImport::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => AttendeeImportStatus::Previewed,
            'original_filename' => 'concurrent.xlsx',
            'summary' => ['stored_path' => $path],
        ]);
        DB::table('ems_attendee_imports')->where('id', $import->id)->update(['created_at' => now()->subHours(25)]);

        // Update to Processing
        $import->status = AttendeeImportStatus::Processing;
        $import->save();

        // Run cleanup
        $job = new \App\Ems\Jobs\CleanupStalePreviewsJob();
        $job->handle();

        // Assert that because status became Processing, cleanup skipped it.
        $this->assertDatabaseHas('ems_attendee_imports', [
            'id' => $import->id,
            'status' => AttendeeImportStatus::Processing->value,
            'deleted_at' => null,
        ]);
        Storage::disk($disk)->assertExists($path);
    }

    protected function setupPaidEventAndRegistration(): array
    {
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addDays(5),
            'capacity' => 100,
        ]);

        $ticketType = TicketType::factory()->paid(20)->create([
            'event_id' => $event->id,
            'name' => 'Paid Ticket',
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 20,
            'buyer_email' => 'paid@example.com',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 20,
            'attendee_email' => 'paid@example.com',
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 20,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Processing->value,
            'provider_checkout_id' => 'checkout_id_test',
        ]);

        return [$event, $ticketType, $order, $registration, $payment];
    }

    public function test_paid_registration_succeeds_and_sends_immediate_email_without_queue(): void
    {
        Mail::fake();
        Queue::fake([SendEventNotificationJob::class, QueueRegistrationConfirmation::class]); // Fake both jobs

        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        // Mark payment as paid, which triggers PaymentFulfillmentService::confirmRegistration()
        app(PaymentFulfillmentService::class)->markPaid($payment, ['provider_payment_id' => 'sq_pay_1234']);

        // Assert registration was confirmed
        $this->assertEquals(RegistrationStatus::Confirmed->value, $registration->fresh()->status->value);

        // Verify that EventNotification record was created with correct values
        $notification = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->firstOrFail();

        $this->assertEquals(NotificationStatus::Sent->value, $notification->status->value);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->failed_at);

        // Verify recipient received mail immediately (synchronously via Mail::fake)
        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($registration) {
            return $mail->hasTo($registration->attendee_email);
        });

        // Verify the QueueRegistrationConfirmation backup job was dispatched
        Queue::assertPushed(QueueRegistrationConfirmation::class, function ($job) use ($registration) {
            return $job->registrationId === $registration->id;
        });

        // Verify the SendEventNotificationJob was NOT dispatched for registration confirmation
        Queue::assertNotPushed(SendEventNotificationJob::class, function ($job) {
            $notification = EventNotification::find($job->notificationId);
            return $notification && $notification->type === NotificationType::RegistrationConfirmed->value;
        });
    }

    public function test_smtp_failure_does_not_rollback_paid_registration(): void
    {
        // Setup Mail to throw SMTP connection failure on send
        Mail::shouldReceive('to')
            ->once()
            ->with('paid@example.com')
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(EventNotificationMail::class))
            ->andThrow(new \RuntimeException("SMTP connection failed"));

        // Expect the admin alert mail to be sent (which is registration failed alert mail)
        Mail::shouldReceive('to')
            ->once()
            ->with(['admin@example.com'])
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(\App\Ems\Mail\RegistrationEmailFailedAlertMail::class));

        config(['ems.notifications.admin_alert_recipients' => 'admin@example.com']);

        // Fake both queue jobs so they do not execute synchronously during test
        Queue::fake([SendEventNotificationJob::class, QueueRegistrationConfirmation::class]);

        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        // Run paid payment fulfillment
        app(PaymentFulfillmentService::class)->markPaid($payment, ['provider_payment_id' => 'sq_pay_5678']);

        // Assert registration remains confirmed (not rolled back)
        $this->assertEquals(RegistrationStatus::Confirmed->value, $registration->fresh()->status->value);

        // Assert notification ledger status is Failed and retry_count is 1
        $notification = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->firstOrFail();

        $this->assertEquals(NotificationStatus::Failed->value, $notification->status->value);
        $this->assertNotNull($notification->failed_at);
        $this->assertEquals(1, $notification->retry_count);
        $this->assertEquals('SMTP connection failed', $notification->error);
    }

    public function test_non_critical_notifications_remain_queued(): void
    {
        Mail::fake();
        Queue::fake();

        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        // Trigger a non-critical notification, e.g. PaymentConfirmation
        app(EventCommunicationService::class)->sendPaymentConfirmation($registration, $payment);

        // Verify that Mail was NOT sent immediately (it should go to the queue first)
        Mail::assertNothingSent();

        // Verify that the SendEventNotificationJob was dispatched to the queue
        Queue::assertPushed(SendEventNotificationJob::class);
    }

    protected function createNotification(array $attributes = []): EventNotification
    {
        $notification = new EventNotification();
        $notification->event_id = $attributes['event_id'] ?? null;
        $notification->registration_id = $attributes['registration_id'] ?? null;
        $notification->type = $attributes['type'] ?? NotificationType::RegistrationConfirmed->value;
        $notification->channel = \App\Ems\Enums\NotificationChannel::Mail;
        $notification->recipient_email = $attributes['recipient_email'] ?? 'test@example.com';
        $notification->subject = 'Test Subject';
        $notification->body = 'Test Body';
        $notification->status = $attributes['status'] ?? NotificationStatus::Pending->value;
        $notification->idempotency_key = $attributes['idempotency_key'] ?? (string) Str::uuid();
        $notification->error = $attributes['error'] ?? null;
        $notification->retry_count = $attributes['retry_count'] ?? 0;
        $notification->save();

        return $notification;
    }

    public function test_free_registration_succeeds_and_sends_immediate_email_without_queue(): void
    {
        Mail::fake();
        Queue::fake();

        $event = Event::factory()->create([
            'status' => EventStatus::RegistrationOpen->value,
            'capacity' => 100,
            'is_public' => true,
        ]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0.00,
            'quantity' => 100,
            'quantity_sold' => 0,
        ]);

        $response = $this->postJson(route('api.ems.public.events.register', $event->slug), [
            'first_name' => 'Free',
            'last_name' => 'Attendee',
            'email' => 'free@example.com',
            'ticket_type_uuid' => $ticketType->uuid,
            'quantity' => 1,
        ]);

        $response->assertStatus(201);

        $registration = Registration::query()
            ->where('attendee_email', 'free@example.com')
            ->firstOrFail();

        // Verify synchronous mail dispatch post-commit
        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($registration) {
            return $mail->hasTo($registration->attendee_email);
        });

        // Verify ledger marked as Sent
        $notification = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();
        $this->assertEquals(NotificationStatus::Sent->value, $notification->status->value);

        // Verify queue job backup was dispatched
        Queue::assertPushed(QueueRegistrationConfirmation::class);
    }

    public function test_queue_backup_idempotency_behavior(): void
    {
        Mail::fake();
        
        $event = Event::factory()->create([
            'status' => EventStatus::RegistrationOpen->value,
            'capacity' => 100,
        ]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0.00,
            'quantity' => 100,
            'quantity_sold' => 0,
        ]);

        // Scenario 1: Immediate send succeeded (status = Sent)
        $registration1 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed->value,
            'attendee_email' => 'success@example.com',
        ]);
        $notification1 = $this->createNotification([
            'event_id' => $event->id,
            'registration_id' => $registration1->id,
            'type' => NotificationType::RegistrationConfirmed->value,
            'recipient_email' => $registration1->attendee_email,
            'status' => NotificationStatus::Sent->value,
            'idempotency_key' => 'registration_confirmed:' . $registration1->id . ':confirm',
        ]);

        Mail::fake(); // Reset Mail assertions
        
        $job1 = new QueueRegistrationConfirmation($registration1->id);
        $job1->handle(app(EventCommunicationService::class));

        // Since it was already Sent, handle should not trigger another send (idempotent)
        Mail::assertNothingSent();

        // Scenario 2: Immediate send failed (status = Failed)
        $registration2 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed->value,
            'attendee_email' => 'failed@example.com',
        ]);
        $notification2 = $this->createNotification([
            'event_id' => $event->id,
            'registration_id' => $registration2->id,
            'type' => NotificationType::RegistrationConfirmed->value,
            'recipient_email' => $registration2->attendee_email,
            'status' => NotificationStatus::Failed->value,
            'idempotency_key' => 'registration_confirmed:' . $registration2->id . ':confirm',
        ]);

        $job2 = new QueueRegistrationConfirmation($registration2->id);
        $job2->handle(app(EventCommunicationService::class));

        // Since status was Failed, the backup job must retry and send email
        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($registration2) {
            return $mail->hasTo($registration2->attendee_email);
        });

        $this->assertEquals(NotificationStatus::Sent->value, $notification2->fresh()->status->value);
    }

    public function test_global_notification_ledger_list_and_filters(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);

        $event = Event::factory()->create();
        $notification1 = $this->createNotification([
            'event_id' => $event->id,
            'recipient_email' => 'user1@example.com',
            'status' => NotificationStatus::Sent->value,
            'type' => NotificationType::RegistrationConfirmed->value,
        ]);
        $notification2 = $this->createNotification([
            'event_id' => $event->id,
            'recipient_email' => 'user2@example.com',
            'status' => NotificationStatus::Failed->value,
            'type' => NotificationType::PaymentConfirmation->value,
        ]);

        // Unauthorized user is blocked
        $this->actingAsEms($staff)
            ->getJson(route('api.ems.notifications.all'))
            ->assertStatus(403);

        // Authorized user can list all
        $response = $this->actingAsEms($admin)
            ->getJson(route('api.ems.notifications.all'))
            ->assertStatus(200);

        $response->assertJsonCount(2, 'data');

        // Apply filters
        $this->actingAsEms($admin)
            ->getJson(route('api.ems.notifications.all', ['status' => 'failed']))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $notification2->uuid);

        $this->actingAsEms($admin)
            ->getJson(route('api.ems.notifications.all', ['search' => 'user1']))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $notification1->uuid);
    }

    public function test_global_notification_retry(): void
    {
        Mail::fake();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);

        $event = Event::factory()->create();

        $notification = $this->createNotification([
            'event_id' => $event->id,
            'recipient_email' => 'retry@example.com',
            'status' => NotificationStatus::Failed->value,
            'type' => NotificationType::RegistrationConfirmed->value,
            'error' => 'Previous SMTP failure',
            'retry_count' => 0,
        ]);

        // Unauthorized user cannot retry
        $this->actingAsEms($staff)
            ->postJson(route('api.ems.notifications.retryGlobal', $notification->uuid))
            ->assertStatus(403);

        // Authorized user can trigger retry
        $this->actingAsEms($admin)
            ->postJson(route('api.ems.notifications.retryGlobal', $notification->uuid))
            ->assertStatus(200);

        // Verify it was resent synchronously and error cleared
        $this->assertEquals(NotificationStatus::Sent->value, $notification->fresh()->status->value);
        $this->assertNull($notification->fresh()->error);

        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('retry@example.com');
        });
    }
}
