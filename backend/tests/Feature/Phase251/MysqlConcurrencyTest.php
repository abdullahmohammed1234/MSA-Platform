<?php

namespace Tests\Feature\Phase251;

use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\CheckoutLifecycleService;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Models\CMS\Announcement;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Genuine multi-process races against an isolated MySQL/MariaDB *_test database.
 * Skipped on SQLite: those results would not be MySQL verification.
 */
class MysqlConcurrencyTest extends TestCase
{
    private const WORKERS = 6;

    /** Tables whose rows created by a test are removed in tearDown (id watermark). */
    private const TRACKED_TABLES = [
        'ems_tickets', 'ems_registrations', 'ems_order_items', 'ems_payments', 'ems_orders',
        'ems_webhook_events', 'ems_notifications', 'ems_ticket_types', 'ems_events', 'ems_event_categories',
        'ems_activity_logs', 'platform_alerts', 'jobs', 'notifications', 'notification_logs', 'announcements', 'cms_revisions',
        'mlibms_loans', 'mlibms_members', 'mlibms_copies', 'mlibms_books', 'volunteer_registrations', 'users',
        'volunteer_digest_runs', 'announcement_deliveries', 'announcement_dispatches',
        // Cleanup runs with FOREIGN_KEY_CHECKS=0, so cascades from users do not fire.
        'notification_preferences', 'job_logs', 'audit_logs',
    ];

    private array $watermarks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        if ($connection !== 'mysql') {
            $this->markTestSkipped("Requires DB_CONNECTION=mysql (current: {$connection}).");
        }
        if (! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            $this->fail('SAFETY GUARD ABORT: database name must end in _test.');
        }

        foreach (self::TRACKED_TABLES as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $this->watermarks[$table] = (int) DB::table($table)->max('id');
            }
        }

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.webhook_signature_key' => 'webhook-secret',
            'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
            'ems.notifications.enabled' => true,
            'queue.default' => 'sync',
        ]);
        Http::fake(fn ($request) => str_contains($request->url(), '/v2/online-checkout/payment-links') && $request->method() === 'POST'
            ? Http::response(['payment_link' => ['id' => 'plink_'.Str::random(8), 'url' => 'https://square.test/x', 'order_id' => 'sq_order_'.Str::random(10)]], 200)
            : Http::response(['objects' => [], 'id_mappings' => []], 200));
    }

    protected function tearDown(): void
    {
        if ($this->watermarks !== []) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->watermarks as $table => $max) {
                DB::table($table)->where('id', '>', $max)->delete();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        parent::tearDown();
    }

    /**
     * @param  list<list<string>>  $jobs  one argument list per worker
     * @return list<array<string, mixed>>
     */
    private function race(array $jobs, array $extraEnv = []): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p251_'.uniqid('', true).'.barrier';
        touch($barrier);

        $env = array_merge([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'ANNOUNCEMENT_EMAIL_HOURLY_CAP' => 'unlimited',
        ], $extraEnv);

        $script = base_path('tests/Feature/ConcurrencyWorkers/p251_worker.php');
        $processes = [];
        foreach ($jobs as $args) {
            $process = new Process(array_merge([PHP_BINARY, $script, $barrier], $args), base_path(), $env, null, 120);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 60;
        while (count(glob($barrier.'.ready.*')) < count($processes) && microtime(true) < $deadline) {
            usleep(20000);
        }
        $this->assertCount(count($processes), glob($barrier.'.ready.*'), 'all workers must reach the barrier');
        unlink($barrier);

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'worker failed: '.$process->getErrorOutput().$process->getOutput());
            $decoded = json_decode($process->getOutput(), true);
            $this->assertIsArray($decoded, 'worker output: '.$process->getOutput());
            $results[] = $decoded;
        }
        array_map('unlink', glob($barrier.'.ready.*'));

        $this->assertCount(count($results), array_unique(array_column($results, 'connection_id')), 'each worker must use its own DB session');

        return $results;
    }

    private function paidEvent(?int $ticketLimit, ?int $capacity = 100): Event
    {
        $category = EventCategory::factory()->create(['is_active' => true]);
        $event = Event::factory()->publiclyDiscoverable()->create([
            'category_id' => $category->id,
            'slug' => 'race-'.Str::lower(Str::random(8)),
            'capacity' => $capacity,
        ]);
        $factory = TicketType::factory()->paid(15);
        if ($ticketLimit !== null) {
            $factory = $factory->limited($ticketLimit);
        }
        $factory->create(['event_id' => $event->id]);

        return $event;
    }

    private function checkoutViaService(Event $event, string $email): Payment
    {
        $out = app(\App\Ems\Services\CheckoutService::class)->checkout($event, [
            'first_name' => 'Seed', 'last_name' => 'Buyer', 'email' => $email,
            'ticket_type_id' => TicketType::query()->where('event_id', $event->id)->value('uuid'), 'quantity' => 1,
        ]);

        return $out['payment'];
    }

    public function test_server_is_mysql_family_and_tables_are_innodb(): void
    {
        $version = DB::selectOne('select version() v')->v;
        $this->assertNotEmpty($version);
        fwrite(STDERR, "\n[p251] server version: {$version}\n");

        $engines = collect(DB::select("SELECT TABLE_NAME t, ENGINE e FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ems_ticket_types','ems_payments','ems_webhook_events','ems_notifications','notification_logs','announcements','mlibms_loans','volunteer_registrations')"))
            ->pluck('e', 't');
        $this->assertCount(8, $engines);
        foreach ($engines as $table => $engine) {
            $this->assertSame('innodb', strtolower($engine), "{$table} must be InnoDB");
        }
    }

    public function test_simultaneous_checkouts_for_the_last_unit_sell_exactly_one(): void
    {
        $event = $this->paidEvent(ticketLimit: 1);

        $results = $this->race(array_map(fn ($i) => ['checkout', (string) $event->id, "buyer{$i}@example.test"], range(1, self::WORKERS)));

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, (int) TicketType::query()->where('event_id', $event->id)->value('quantity_sold'));
        $this->assertSame(1, Payment::query()->whereHas('order', fn ($q) => $q->where('event_id', $event->id))->count());
    }

    public function test_simultaneous_checkouts_respect_event_capacity(): void
    {
        $event = $this->paidEvent(ticketLimit: null, capacity: 2);

        $results = $this->race(array_map(fn ($i) => ['checkout', (string) $event->id, "cap{$i}@example.test"], range(1, self::WORKERS)));

        $this->assertSame(2, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(2, $event->fresh()->occupiedSeats());
    }

    public function test_late_payment_racing_new_checkouts_never_oversells(): void
    {
        $event = $this->paidEvent(ticketLimit: 1);
        $late = $this->checkoutViaService($event, 'late@example.test');
        $late->forceFill(['checkout_expires_at' => now()->subMinute()])->save();
        app(CheckoutLifecycleService::class)->expireStale();
        $this->assertSame(PaymentStatus::Abandoned, $late->fresh()->status);

        $jobs = [['mark_paid', (string) $late->id, 'sq_pay_late_race']];
        foreach (range(1, self::WORKERS - 1) as $i) {
            $jobs[] = ['checkout', (string) $event->id, "racer{$i}@example.test"];
        }
        $results = $this->race($jobs);

        $soldTo = Registration::query()->where('event_id', $event->id)->get()->filter(fn ($r) => $r->status->occupiesCapacity());
        $this->assertLessThanOrEqual(1, $soldTo->count(), json_encode($results));
        $this->assertSame(1, (int) TicketType::query()->where('event_id', $event->id)->value('quantity_sold'));

        $late = $late->fresh();
        if ($late->status === PaymentStatus::Paid) {
            $this->assertSame(0, collect($results)->where('action', 'checkout')->where('ok', true)->count());
        } else {
            $this->assertSame(PaymentStatus::Cancelled, $late->status);
            $this->assertTrue($late->holdsUnfulfilledCaptures());
        }
        fwrite(STDERR, "\n[p251] late-payment race winner: ".($late->status === PaymentStatus::Paid ? 'late payment' : 'new checkout')."\n");
    }

    public function test_webhook_claim_has_exactly_one_winner(): void
    {
        $row = WebhookEvent::query()->create(['provider' => 'square', 'event_id' => 'evt_claim_'.Str::random(8), 'event_type' => 'payment.updated', 'status' => WebhookEventStatus::Received->value, 'payload' => []]);

        $results = $this->race(array_fill(0, self::WORKERS, ['webhook_claim', (string) $row->id]));

        $this->assertSame(1, collect($results)->where('claimed', true)->count());
        $this->assertSame(1, (int) $row->fresh()->retry_count);
    }

    public function test_stale_processing_lease_is_reclaimed_by_exactly_one_worker(): void
    {
        $row = WebhookEvent::query()->create(['provider' => 'square', 'event_id' => 'evt_stale_'.Str::random(8), 'event_type' => 'payment.updated', 'status' => WebhookEventStatus::Processing->value, 'payload' => [], 'retry_count' => 1, 'last_attempt_at' => now()->subMinutes(30)]);

        $results = $this->race(array_fill(0, self::WORKERS, ['webhook_claim', (string) $row->id]));

        $this->assertSame(1, collect($results)->where('claimed', true)->count());
        $this->assertSame(2, (int) $row->fresh()->retry_count);
    }

    public function test_concurrent_processing_of_one_webhook_fulfils_once(): void
    {
        $event = $this->paidEvent(ticketLimit: 5);
        $payment = $this->checkoutViaService($event, 'webhook@example.test');
        $row = WebhookEvent::query()->create(['provider' => 'square', 'event_id' => 'evt_proc_'.Str::random(8), 'event_type' => 'payment.updated', 'status' => WebhookEventStatus::Received->value, 'payload' => $this->paidPayload($payment)]);

        $this->race(array_fill(0, self::WORKERS, ['webhook_process', (string) $row->id]));

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(1, (int) $row->fresh()->retry_count, 'only one worker may claim the row');
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
    }

    public function test_concurrent_first_deliveries_of_the_same_webhook_create_one_row(): void
    {
        $event = $this->paidEvent(ticketLimit: 5);
        $payment = $this->checkoutViaService($event, 'firstdelivery@example.test');
        $eventId = 'evt_first_'.Str::random(8);
        $body = json_encode($this->paidPayload($payment, $eventId), JSON_THROW_ON_ERROR);
        $bodyFile = tempnam(sys_get_temp_dir(), 'p251body');
        file_put_contents($bodyFile, $body);
        $signature = base64_encode(hash_hmac('sha256', 'https://example.test/api/v1/webhooks/square'.$body, 'webhook-secret', true));

        try {
            $results = $this->race(array_fill(0, self::WORKERS, ['webhook_handle', $bodyFile, $signature]));
        } finally {
            @unlink($bodyFile);
        }

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, WebhookEvent::query()->where('event_id', $eventId)->count());
        $this->assertSame(WebhookEventStatus::Processed->value, WebhookEvent::query()->where('event_id', $eventId)->value('status'));
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
    }

    public function test_event_notification_claim_has_exactly_one_winner(): void
    {
        $notification = $this->eventNotification(NotificationStatus::Pending);

        $results = $this->race(array_fill(0, self::WORKERS, ['notif_claim', (string) $notification->id]));

        $this->assertSame(1, collect($results)->where('claimed', true)->count());
    }

    public function test_admin_retries_racing_the_sweeper_enqueue_one_send(): void
    {
        $notification = $this->eventNotification(NotificationStatus::Failed);

        $jobs = [];
        foreach (range(1, self::WORKERS) as $i) {
            $jobs[] = $i % 2 ? ['ems_retry', (string) $notification->id] : ['ems_sweep'];
        }
        $results = $this->race($jobs, ['QUEUE_CONNECTION' => 'database']);

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $queued = DB::table('jobs')->where('payload', 'like', '%SendEventNotificationJob%')->get()
            ->filter(fn ($job) => str_contains($job->payload, 's:14:\"notificationId\";i:'.$notification->id.';'))
            ->count();
        $this->assertSame(1, $queued, 'exactly one send job for the retried notification');
    }

    public function test_concurrent_admin_resends_have_exactly_one_winner(): void
    {
        $user = User::factory()->create();
        $log = NotificationLog::create(['user_id' => $user->id, 'notification_type' => 'X', 'channel' => 'email', 'status' => 'failed']);

        $results = $this->race(array_fill(0, self::WORKERS, ['resend_claim', (string) $log->id]));

        $this->assertSame(1, collect($results)->where('claimed', true)->count());
        $this->assertSame('resending', $log->fresh()->status);
    }

    public function test_concurrent_publishes_of_one_announcement_notify_once(): void
    {
        $announcement = Announcement::query()->create(['uuid' => (string) Str::uuid(), 'title' => 'Race', 'slug' => 'race-'.Str::lower(Str::random(8)), 'content' => 'x', 'status' => 'draft']);

        $results = $this->race(array_fill(0, self::WORKERS, ['announce_publish', (string) $announcement->id]));

        $this->assertSame(1, array_sum(array_column($results, 'dispatched')), json_encode($results));
        $this->assertNotNull($announcement->fresh()->notified_at);
    }

    public function test_concurrent_mlibms_runs_send_each_notice_once(): void
    {
        $reminderLoans = [$this->loan(now()->addDays(2)), $this->loan(now()->addDays(2))];
        $overdueLoan = $this->loan(now()->subDay());

        $results = $this->race(array_fill(0, self::WORKERS, ['mlibms']));

        $this->assertSame(2, array_sum(array_column($results, 'reminders')), json_encode($results));
        $this->assertSame(1, array_sum(array_column($results, 'overdue')), json_encode($results));
        foreach ($reminderLoans as $loan) {
            $this->assertNotNull($loan->fresh()->reminder_sent_at);
        }
        $this->assertSame('overdue', (string) ($overdueLoan->fresh()->status->value ?? $overdueLoan->fresh()->status));
    }

    public function test_concurrent_digest_runs_send_one_digest(): void
    {
        $cutoff = \App\Models\VolunteerDigestRun::latestDueCutoff();
        DB::table('volunteer_digest_runs')->where('period_date', $cutoff->toDateString())->delete();
        foreach (range(1, 3) as $i) {
            DB::table('volunteer_registrations')->insert([
                'uuid' => (string) Str::uuid(), 'name' => "Race {$i}", 'email' => "race{$i}@example.test",
                'student_number' => '300000000', 'department' => 'Synthetic', 'interests' => '[]',
                'status' => 'new', 'created_at' => $cutoff->copy()->subHour(), 'updated_at' => now(),
            ]);
        }
        DB::table('volunteer_registrations')->insert([
            'uuid' => (string) Str::uuid(), 'name' => 'Race late', 'email' => 'race-late@example.test',
            'student_number' => '300000000', 'department' => 'Synthetic', 'interests' => '[]',
            'status' => 'new', 'created_at' => $cutoff->copy()->addSecond(), 'updated_at' => now(),
        ]);

        $results = $this->race(array_fill(0, self::WORKERS, ['digest']));

        $this->assertSame(1, array_sum(array_column($results, 'digests')), json_encode($results));
        $this->assertSame(1, DB::table('volunteer_registrations')->where('name', 'like', 'Race %')->where('name', '!=', 'Race late')->distinct()->count('digest_batch'));
        $this->assertNull(DB::table('volunteer_registrations')->where('name', 'Race late')->value('digest_batch'), 'post-cutoff row stays for the next period');

        $run = DB::table('volunteer_digest_runs')->where('period_date', $cutoff->toDateString())->first();
        $this->assertSame('sent', $run->status);
        $this->assertSame($cutoff->format('Y-m-d H:i:s'), (string) $run->cutoff_at, 'cutoff must survive the claim/finish updates');
    }

    public function test_concurrent_announcement_senders_deliver_each_email_once(): void
    {
        $emails = [];
        foreach (range(1, 12) as $i) {
            $emails[] = $email = "p26-member-{$i}-".Str::lower(Str::random(5)).'@example.test';
            \App\Models\User::factory()->create(['is_active' => true, 'email' => $email]);
        }

        config(['queue.default' => 'database']);
        $service = app(\App\Services\Announcements\AnnouncementDeliveryService::class);
        $dispatch = $service->start(\App\Models\AnnouncementDispatch::KIND_BROADCAST, 'broadcast:p26-race-'.Str::uuid(), [
            'title' => 'Race', 'excerpt' => 'Race', 'slug' => '', 'audience' => 'All',
        ], null, null);
        $service->prepare($dispatch);
        $dispatch->refresh();
        $this->assertSame(\App\Models\AnnouncementDispatch::STATUS_SENDING, $dispatch->status);

        $results = $this->race(array_fill(0, self::WORKERS, ['announce_send', (string) $dispatch->id]));

        $sent = array_merge(...array_map(fn ($r) => $r['emails'] ?? [], $results));
        $mine = array_values(array_filter($sent, fn ($e) => in_array($e, $emails, true)));
        sort($mine);
        $expected = $emails;
        sort($expected);
        $this->assertSame($expected, $mine, 'every member exactly once across all workers: '.json_encode($results));

        $rows = \App\Models\AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id);
        $this->assertSame(0, (clone $rows)->where('status', '!=', \App\Models\AnnouncementDelivery::STATUS_SENT)->count());
        $this->assertSame(0, (clone $rows)->where('attempts', '!=', 1)->count(), 'no row was claimed twice');
    }

    public function test_concurrent_capped_senders_never_exceed_the_hourly_cap(): void
    {
        $emails = [];
        foreach (range(1, 12) as $i) {
            $emails[] = $email = "p28-member-{$i}-".Str::lower(Str::random(5)).'@example.test';
            \App\Models\User::factory()->create(['is_active' => true, 'email' => $email]);
        }
        DB::table('cache_locks')->where('key', 'like', '%announcements:email-sender')->delete();

        config(['queue.default' => 'database']);
        $service = app(\App\Services\Announcements\AnnouncementDeliveryService::class);
        $dispatch = $service->start(\App\Models\AnnouncementDispatch::KIND_BROADCAST, 'broadcast:p28-cap-race-'.Str::uuid(), [
            'title' => 'Cap race', 'excerpt' => 'Cap race', 'slug' => '', 'audience' => 'All',
        ], null, null);
        $service->prepare($dispatch);

        $results = $this->race(
            array_fill(0, self::WORKERS, ['announce_send', (string) $dispatch->id]),
            ['CACHE_STORE' => 'database', 'ANNOUNCEMENT_EMAIL_HOURLY_CAP' => '5'],
        );

        $sent = array_merge(...array_map(fn ($r) => $r['emails'] ?? [], $results));
        $this->assertCount(5, $sent, 'exactly the cap is sent across all workers: '.json_encode($results));
        $this->assertCount(5, array_unique($sent));

        $attempted = \App\Models\AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('channel', \App\Models\AnnouncementDelivery::CHANNEL_EMAIL)->where('attempts', '>', 0)->count();
        $this->assertSame(5, $attempted);
        $this->assertSame(0, DB::table('cache_locks')->where('key', 'like', '%announcements:email-sender')->count(), 'the lock is released');
    }

    public function test_concurrent_operator_requeues_win_once(): void
    {
        $id = DB::table('ems_webhook_events')->insertGetId([
            'uuid' => (string) Str::uuid(), 'provider' => 'square', 'event_id' => 'evt_p26_'.Str::random(8),
            'event_type' => 'payment.updated', 'status' => 'needs_review', 'retry_count' => 6,
            'payload' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $results = $this->race(array_fill(0, self::WORKERS, ['webhook_requeue', (string) $id]));

        $this->assertSame(1, count(array_filter(array_column($results, 'requeued'))), json_encode($results));
        $this->assertSame('retry_pending', DB::table('ems_webhook_events')->where('id', $id)->value('status'));
    }

    public function test_phase26_tables_have_no_auto_updating_timestamp_columns(): void
    {
        $columns = DB::select(
            "select table_name t, column_name c from information_schema.columns where table_schema = database()
             and table_name in ('volunteer_digest_runs', 'announcement_dispatches', 'announcement_deliveries', 'ems_webhook_events')
             and lower(extra) like '%on update%'"
        );

        $this->assertSame([], array_map(fn ($r) => $r->t.'.'.$r->c, $columns));
    }

    private function paidPayload(Payment $payment, ?string $eventId = null): array
    {
        $payment->loadMissing('order');

        return [
            'event_id' => $eventId ?? 'evt_'.$payment->id,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_'.$payment->id,
                'status' => 'COMPLETED',
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $payment->order->reference,
            ]]],
        ];
    }

    private function eventNotification(NotificationStatus $status): EventNotification
    {
        $event = $this->paidEvent(ticketLimit: null);
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed, 'attendee_email' => 'n'.Str::random(6).'@example.test']);

        return EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Subject',
            'body' => 'Body',
            'status' => $status->value,
            'scheduled_at' => now()->subMinute(),
            'failed_at' => $status === NotificationStatus::Failed ? now()->subMinute() : null,
            'idempotency_key' => 'p251:'.Str::uuid(),
        ]);
    }

    private function loan(\Illuminate\Support\Carbon $dueAt): Loan
    {
        $suffix = Str::upper(Str::random(6));
        $user = User::factory()->create();
        $book = Book::create(['title' => 'Book '.$suffix, 'slug' => 'book-'.Str::lower($suffix)]);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'C-'.$suffix, 'accession_number' => 'A-'.$suffix, 'status' => 'checked_out']);
        $member = Member::create(['user_id' => $user->id, 'library_card_number' => 'CARD-'.$suffix, 'name' => $user->name, 'email' => $user->email]);

        return Loan::create(['copy_id' => $copy->id, 'member_id' => $member->id, 'checked_out_at' => now()->subDays(12), 'due_at' => $dueAt, 'status' => 'active']);
    }
}
