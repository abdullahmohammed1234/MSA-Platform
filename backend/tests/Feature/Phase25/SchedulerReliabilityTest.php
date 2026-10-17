<?php

namespace Tests\Feature\Phase25;

use App\Jobs\Analytics\AggregateAnalyticsMetricsJob;
use App\Jobs\Reports\GenerateDailyReportJob;
use App\Jobs\Reports\GenerateMonthlyReportJob;
use App\Jobs\Reports\GenerateWeeklyReportJob;
use App\Mail\DailyVolunteerDigestMail;
use App\Mlibms\Mail\HoldExpiredMail;
use App\Mlibms\Mail\HoldReadyForPickupMail;
use App\Mlibms\Mail\LoanDueDateReminderMail;
use App\Mlibms\Mail\LoanOverdueNoticeMail;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Reservation;
use App\Mlibms\Services\LoanService;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Platform\Models\PlatformAlert;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Overlap is simulated by mutating rows between the command's candidate query
 * and its claim. This proves the claim/re-check logic; it does not prove MySQL
 * locking under real parallel processes.
 */
class SchedulerReliabilityTest extends TestCase
{
    use RefreshDatabase;

    // --- Schedule definitions -------------------------------------------------

    private function scheduledEvents(): array
    {
        Artisan::call('schedule:list');

        return app(Schedule::class)->events();
    }

    private function eventFor(string $needle): ?\Illuminate\Console\Scheduling\Event
    {
        foreach ($this->scheduledEvents() as $event) {
            if (str_contains((string) $event->command, $needle)) {
                return $event;
            }
        }

        return null;
    }

    public function test_token_pruning_is_scheduled_daily_with_non_negative_grace(): void
    {
        $event = $this->eventFor('sanctum:prune-expired');

        $this->assertNotNull($event, 'sanctum:prune-expired is not scheduled');
        $this->assertStringContainsString('--hours=24', $event->command);
        $this->assertDoesNotMatchRegularExpression('/--hours=-/', $event->command);
        $this->assertSame('30 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_every_scheduled_artisan_command_is_overlap_protected_with_bounded_lock(): void
    {
        $commands = array_filter($this->scheduledEvents(), fn ($e) => str_contains((string) $e->command, 'artisan'));

        $this->assertNotEmpty($commands);
        foreach ($commands as $event) {
            $this->assertTrue($event->withoutOverlapping, "{$event->command} lacks withoutOverlapping");
            $this->assertLessThanOrEqual(120, $event->expiresAt, "{$event->command} lock expiry is unbounded");
        }
    }

    public function test_scheduled_sweeper_jobs_are_unique_and_retry_settings_are_bounded(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, new \App\Ems\Jobs\ProcessDueNotificationsJob());
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, new \App\Ems\Jobs\ProcessDueRemindersJob());

        $aggregate = new AggregateAnalyticsMetricsJob();
        $this->assertSame(3, $aggregate->tries);
        $this->assertFalse(method_exists($aggregate, 'retryUntil'));

        foreach ([GenerateDailyReportJob::class, GenerateWeeklyReportJob::class, GenerateMonthlyReportJob::class] as $class) {
            $this->assertSame(1, (new $class())->tries, $class);
        }
    }

    // --- MLibMS ---------------------------------------------------------------

    private function loan(array $attributes = []): Loan
    {
        $suffix = Str::upper(Str::random(6));
        $user = User::factory()->create();
        $book = Book::create(['title' => 'Book '.$suffix, 'slug' => 'book-'.Str::lower($suffix)]);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'C-'.$suffix, 'accession_number' => 'A-'.$suffix, 'status' => 'checked_out']);
        $member = Member::create(['user_id' => $user->id, 'library_card_number' => 'CARD-'.$suffix, 'name' => $user->name, 'email' => $user->email]);

        return Loan::create(array_merge([
            'copy_id' => $copy->id,
            'member_id' => $member->id,
            'checked_out_at' => now()->subDays(12),
            'due_at' => now()->addDays(2),
            'status' => 'active',
        ], $attributes));
    }

    public function test_overdue_notice_is_not_sent_when_a_concurrent_run_already_claimed_the_loan(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => true]);
        $loan = $this->loan(['due_at' => now()->subDay()]);

        Loan::retrieved(function (Loan $retrieved) use ($loan): void {
            if ($retrieved->id === $loan->id) {
                DB::table('mlibms_loans')->where('id', $loan->id)->update(['status' => 'overdue']);
            }
        });

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        Mail::assertNotQueued(LoanOverdueNoticeMail::class);
    }

    public function test_overdue_notice_is_queued_once_without_interference(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => true]);
        $loan = $this->loan(['due_at' => now()->subDay()]);

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);
        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        Mail::assertQueued(LoanOverdueNoticeMail::class, 1);
        $this->assertSame('overdue', $loan->fresh()->status->value);
    }

    public function test_reminder_claim_is_released_when_queueing_fails(): void
    {
        config(['features.mlibms_overdue_processing_enabled' => true]);
        $loan = $this->loan();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('queue unavailable'));

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        $this->assertNull($loan->fresh()->reminder_sent_at);
    }

    public function test_renewal_rearms_due_date_reminder(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => true]);
        $loan = $this->loan();

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);
        $this->assertNotNull($loan->fresh()->reminder_sent_at);

        app(LoanService::class)->renewLoan($loan->fresh(), User::factory()->create());

        $this->assertNull($loan->fresh()->reminder_sent_at);
        $this->travelTo($loan->fresh()->due_at->copy()->subDays(2)->setTime(8, 0));
        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);
        Mail::assertQueued(LoanDueDateReminderMail::class, 2);
    }

    private function expiredHoldWithWaitingMember(): array
    {
        $loan = $this->loan();
        $copy = Copy::findOrFail($loan->copy_id);
        $loan->update(['status' => 'returned', 'returned_at' => now()]);
        $copy->update(['status' => 'reserved']);

        $expired = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'book_id' => $copy->book_id,
            'copy_id' => $copy->id,
            'member_id' => $loan->member_id,
            'status' => 'ready_for_pickup',
            'queue_position' => 1,
            'reserved_at' => now()->subDays(6),
            'ready_at' => now()->subDays(4),
            'expires_at' => now()->subDay(),
        ]);

        $otherUser = User::factory()->create();
        $otherMember = Member::create(['user_id' => $otherUser->id, 'library_card_number' => 'CARD-W'.Str::random(4), 'name' => $otherUser->name, 'email' => $otherUser->email]);
        $waiting = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'book_id' => $copy->book_id,
            'member_id' => $otherMember->id,
            'status' => 'pending',
            'queue_position' => 2,
            'reserved_at' => now()->subDays(5),
        ]);

        return [$expired, $waiting];
    }

    public function test_hold_already_processed_by_concurrent_run_is_not_expired_twice(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => true]);
        [$expired, $waiting] = $this->expiredHoldWithWaitingMember();

        Reservation::retrieved(function (Reservation $retrieved) use ($expired): void {
            if ($retrieved->id === $expired->id) {
                DB::table('mlibms_reservations')->where('id', $expired->id)->update(['status' => 'expired']);
            }
        });

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        $this->assertSame('pending', $waiting->fresh()->status->value);
        Mail::assertNotQueued(HoldExpiredMail::class);
        Mail::assertNotQueued(HoldReadyForPickupMail::class);
    }

    public function test_expired_hold_passes_copy_to_next_member_without_interference(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => true]);
        [$expired, $waiting] = $this->expiredHoldWithWaitingMember();

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);
        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        $this->assertSame('expired', $expired->fresh()->status->value);
        $this->assertSame('ready_for_pickup', $waiting->fresh()->status->value);
        Mail::assertQueued(HoldExpiredMail::class, 1);
        Mail::assertQueued(HoldReadyForPickupMail::class, 1);
    }

    // --- Volunteer digest -----------------------------------------------------

    public function test_digest_includes_late_registrations_next_run_and_never_resends(): void
    {
        Mail::fake();
        config(['features.volunteer_digest_enabled' => true]);

        $this->travelTo(Carbon::parse('2026-10-13 23:50:00'));
        VolunteerRegistration::factory()->create(['email' => 'early@sfu.ca', 'created_at' => now()]);
        $this->travelTo(Carbon::parse('2026-10-13 23:55:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);

        $this->travelTo(Carbon::parse('2026-10-13 23:57:00'));
        VolunteerRegistration::factory()->create(['email' => 'late@sfu.ca', 'created_at' => now()]);

        $this->travelTo(Carbon::parse('2026-10-14 23:55:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);

        $sent = Mail::sent(DailyVolunteerDigestMail::class)->values();
        $this->assertCount(2, $sent);
        $this->assertSame(['early@sfu.ca'], $sent[0]->registrations->pluck('email')->all());
        $this->assertSame(['late@sfu.ca'], $sent[1]->registrations->pluck('email')->all());
    }

    public function test_digest_failure_releases_rows_for_the_next_run(): void
    {
        config(['features.volunteer_digest_enabled' => true]);
        $this->travelTo(Carbon::parse('2026-10-13 12:00:00'));
        $registration = VolunteerRegistration::factory()->create(['created_at' => now()]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->travelTo(Carbon::parse('2026-10-13 23:55:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(1);

        $this->assertNull(DB::table('volunteer_registrations')->where('id', $registration->id)->value('digested_at'));
    }

    // --- Monitoring -----------------------------------------------------------

    private function alertExists(string $key, string $application): bool
    {
        return PlatformAlert::query()->where('alert_key', md5($key.':'.$application))->exists();
    }

    public function test_stale_cron_heartbeat_raises_alert(): void
    {
        Cache::put('platform.cron.last_heartbeat', now()->subMinutes(30)->toIso8601String(), 86400);

        app(PlatformAlertService::class)->evaluateAndTriggerAlerts();

        $this->assertTrue($this->alertExists('cron.heartbeat_stale', 'system'));
    }

    public function test_fresh_cron_heartbeat_does_not_alert(): void
    {
        Cache::put('platform.cron.last_heartbeat', now()->subMinute()->toIso8601String(), 86400);

        app(PlatformAlertService::class)->evaluateAndTriggerAlerts();

        $this->assertFalse($this->alertExists('cron.heartbeat_stale', 'system'));
    }

    private function pushRawJob(int $availableMinutesAgo): void
    {
        DB::table('jobs')->insert([
            'queue' => 'ems-notifications',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes($availableMinutesAgo)->getTimestamp(),
            'created_at' => now()->subMinutes($availableMinutesAgo)->getTimestamp(),
        ]);
    }

    public function test_stalled_queue_raises_alert(): void
    {
        $this->pushRawJob(20);

        app(PlatformAlertService::class)->evaluateAndTriggerAlerts();

        $this->assertTrue($this->alertExists('queue.stalled', 'queues'));
    }

    public function test_recent_queued_job_does_not_raise_stall_alert(): void
    {
        $this->pushRawJob(2);

        app(PlatformAlertService::class)->evaluateAndTriggerAlerts();

        $this->assertFalse($this->alertExists('queue.stalled', 'queues'));
    }

    public function test_scheduled_task_failure_is_logged_and_alerted_without_exception_message(): void
    {
        Log::spy();
        $task = app(Schedule::class)->command('vms:send-reminders');

        event(new ScheduledTaskFailed($task, new \RuntimeException('SMTP rejected volunteer@example.com')));

        $alert = PlatformAlert::query()
            ->where('alert_key', md5('scheduler.task_failed.'.Str::slug('vms:send-reminders').':system'))
            ->first();
        $this->assertNotNull($alert);
        $this->assertStringNotContainsString('volunteer@example.com', $alert->message.json_encode($alert->context));
        Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => $message === 'scheduler.task_failed'
                && $context['task'] === 'vms:send-reminders'
                && ! str_contains(json_encode($context), 'volunteer@example.com')
        )->once();
    }
}
