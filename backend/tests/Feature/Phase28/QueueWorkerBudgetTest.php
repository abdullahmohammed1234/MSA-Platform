<?php

namespace Tests\Feature\Phase28;

use App\Jobs\Announcements\RecoverAnnouncementDeliveriesJob;
use App\Jobs\Announcements\SendAnnouncementDeliveriesJob;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Models\Role;
use App\Models\User;
use App\Services\Announcements\AnnouncementDeliveryService;
use App\Services\Queue\QueueManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

/**
 * The cron-driven worker (`queue:work --stop-when-empty --max-time=N`) under a hard
 * per-process limit: it must stop on budget, leave the rest for the next invocation,
 * and recover a job whose process was killed mid-run without repeating its effects.
 */
class QueueWorkerBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<int> */
    public static array $processed = [];

    /** @var array<string, int> */
    public static array $delivered = [];

    public static bool $flakyFails = true;

    protected function setUp(): void
    {
        parent::setUp();

        self::$processed = [];
        self::$delivered = [];
        self::$flakyFails = true;

        Mail::extend('p28-worker-recording', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                foreach ($message->getEnvelope()->getRecipients() as $address) {
                    /** @var Address $address */
                    QueueWorkerBudgetTest::$delivered[$address->getAddress()] = (QueueWorkerBudgetTest::$delivered[$address->getAddress()] ?? 0) + 1;
                }
            }

            public function __toString(): string
            {
                return 'p28-worker-recording://';
            }
        });

        config([
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 90,
            'queue.failed.driver' => 'database-uuids',
            'mail.mailers.p28-worker-recording' => ['transport' => 'p28-worker-recording'],
            'mail.default' => 'p28-worker-recording',
        ]);
    }

    /** One cron invocation of the worker; returns elapsed seconds. */
    private function workerInvocation(int $maxTime = 1, string $queue = 'default'): float
    {
        $start = microtime(true);
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => $queue,
            '--stop-when-empty' => true,
            '--max-time' => $maxTime,
            '--tries' => 3,
            '--sleep' => 0,
            '--memory' => 1024,
        ]);

        return microtime(true) - $start;
    }

    public function test_worker_stops_on_its_time_budget_and_the_backlog_drains_over_later_invocations(): void
    {
        foreach (range(1, 8) as $i) {
            P28SlowJob::dispatch($i);
        }

        $elapsed = [];
        while (DB::table('jobs')->count() > 0 && count($elapsed) < 10) {
            $elapsed[] = $this->workerInvocation(maxTime: 1);
        }

        $this->assertGreaterThanOrEqual(2, count($elapsed), 'the backlog needs more than one invocation');
        foreach ($elapsed as $seconds) {
            // max-time is checked between jobs: worst case is max-time plus one job (0.4s), plus overhead.
            $this->assertLessThan(1 + 0.4 + 1.0, $seconds);
        }
        $this->assertSame(range(1, 8), self::$processed, 'every job ran exactly once, in order');
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function broadcastTo(int $members): AnnouncementDispatch
    {
        foreach (range(1, $members) as $i) {
            User::factory()->create(['is_active' => true, 'email' => "worker{$i}@example.test"]);
        }
        $service = app(AnnouncementDeliveryService::class);
        $dispatch = $service->start(AnnouncementDispatch::KIND_BROADCAST, 'broadcast:p28-worker-'.Str::uuid(), [
            'title' => 'Iftar schedule', 'excerpt' => 'Details', 'slug' => '', 'audience' => 'All',
        ]);
        $service->prepare($dispatch);
        DB::table('jobs')->delete();

        return $dispatch->refresh();
    }

    public function test_job_killed_mid_run_is_recovered_without_repeating_sent_email(): void
    {
        $dispatch = $this->broadcastTo(3);
        SendAnnouncementDeliveriesJob::dispatch($dispatch->id);

        // The host killed the previous worker at its process limit: the queue row is still
        // reserved, and one email row was claimed but its message never left.
        $killedAt = now()->subSeconds(100);
        DB::table('jobs')->update(['reserved_at' => $killedAt->getTimestamp(), 'attempts' => 1]);
        $stuck = AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_EMAIL)->orderBy('id')->firstOrFail();
        $stuck->forceFill(['status' => AnnouncementDelivery::STATUS_SENDING, 'attempts' => 1, 'last_attempt_at' => $killedAt])->save();

        // Next cron invocation: retry_after (90s) has passed, so the job is picked up again.
        $this->workerInvocation();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(2, count(self::$delivered), 'the other recipients are served');
        $this->assertSame(AnnouncementDelivery::STATUS_SENDING, $stuck->fresh()->status, 'a claim inside its lease is left alone');

        // After the claim lease expires, the scheduled sweep resumes the stuck row.
        $this->travel(11)->minutes();
        RecoverAnnouncementDeliveriesJob::dispatch();
        $this->workerInvocation();
        $this->workerInvocation();

        $this->assertSame(3, count(self::$delivered));
        $this->assertSame([1], array_values(array_unique(self::$delivered)), 'nobody received the announcement twice');
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);

        // Duplicate invocations and duplicate queued copies change nothing.
        SendAnnouncementDeliveriesJob::dispatch($dispatch->id);
        SendAnnouncementDeliveriesJob::dispatch($dispatch->id);
        RecoverAnnouncementDeliveriesJob::dispatch();
        $this->workerInvocation();
        $this->workerInvocation();

        $this->assertSame([1], array_values(array_unique(self::$delivered)));
        $this->assertSame(3, array_sum(self::$delivered));
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_job_that_exhausts_its_tries_is_visible_and_can_be_requeued(): void
    {
        P28FlakyJob::dispatch();

        $this->workerInvocation();

        $failed = app(QueueManagementService::class)->getFailedJobs();
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertStringContainsString('P28FlakyJob', json_encode($failed));
        $this->assertSame(0, DB::table('jobs')->count());

        // The cause is fixed; an operator requeues it and the next invocation completes it.
        self::$flakyFails = false;
        $uuid = (string) DB::table('failed_jobs')->value('uuid');
        $this->assertTrue(app(QueueManagementService::class)->retryJob($uuid));
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->workerInvocation();

        $this->assertSame([99], self::$processed);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_registration_and_password_reset_do_not_wait_for_the_worker(): void
    {
        Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);
        Role::create(['name' => 'Member', 'slug' => 'member']);

        $this->postJson(route('api.auth.register'), [
            'name' => 'Queue Student',
            'email' => 'queue.student@sfu.ca',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'role' => 'volunteer',
        ])->assertCreated()->assertJsonPath('verification_email_sent', true);

        // The verification email went out during the request; nothing waits in the queue for it.
        $this->assertSame(['queue.student@sfu.ca' => 1], self::$delivered);
        $this->assertSame(0, DB::table('jobs')->count());

        // The reset request completes immediately; its email waits for the next worker pass.
        $this->postJson(route('api.auth.forgot-password'), ['email' => 'queue.student@sfu.ca'])->assertOk();
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'queue.student@sfu.ca')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, self::$delivered['queue.student@sfu.ca']);

        $this->workerInvocation(queue: 'default,low,high');
        $this->assertSame(2, self::$delivered['queue.student@sfu.ca']);
    }
}

class P28SlowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $number)
    {
    }

    public function handle(): void
    {
        usleep(400_000);
        QueueWorkerBudgetTest::$processed[] = $this->number;
    }
}

class P28FlakyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        if (QueueWorkerBudgetTest::$flakyFails) {
            throw new RuntimeException('Simulated dependency outage');
        }
        QueueWorkerBudgetTest::$processed[] = 99;
    }
}
