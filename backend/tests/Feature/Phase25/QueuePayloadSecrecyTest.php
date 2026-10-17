<?php

namespace Tests\Feature\Phase25;

use App\Jobs\Maintenance\ArchiveOldLogsJob;
use App\Models\JobLog;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Queue\JobMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QueuePayloadSecrecyTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_TOKEN = 'phase25-reset-token-6f1c2b';

    private const VERIFY_TOKEN = 'phase25-verify-token-91aa0d';

    public function test_auth_notification_tokens_are_not_stored_in_plaintext_queue_payloads(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();

        $user->notify(new ResetPasswordNotification(self::RESET_TOKEN, $user->email));
        $user->notify(new VerifyEmailNotification(self::VERIFY_TOKEN));

        $payloads = DB::table('jobs')->pluck('payload')->implode("\n");

        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertStringNotContainsString(self::RESET_TOKEN, $payloads);
        $this->assertStringNotContainsString(self::VERIFY_TOKEN, $payloads);
        $this->assertStringNotContainsString($user->email, $payloads);
    }

    public function test_worker_job_log_keeps_only_payload_metadata(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $user->notify(new ResetPasswordNotification(self::RESET_TOKEN, $user->email));

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        $log = JobLog::query()->firstOrFail();
        $this->assertSame('completed', $log->status);
        $this->assertSame(ResetPasswordNotification::class, $log->payload['displayName']);
        $this->assertArrayNotHasKey('data', $log->payload);
        $this->assertStringNotContainsString(self::RESET_TOKEN, (string) DB::table('job_logs')->value('payload'));
        $this->assertArrayNotHasKey('payload', $log->toArray());
    }

    public function test_job_log_archive_excludes_payload(): void
    {
        Storage::fake();
        $log = JobLog::query()->create([
            'job_uuid' => 'phase25-archive',
            'job_name' => 'Legacy',
            'queue' => 'default',
            'status' => 'completed',
            'payload' => ['data' => ['command' => 'legacy '.self::RESET_TOKEN]],
        ]);
        $log->forceFill(['created_at' => now()->subDays(40)])->save();

        (new ArchiveOldLogsJob())->handle();

        $files = Storage::allFiles('archives');
        $this->assertCount(1, $files);
        $contents = Storage::get($files[0]);
        $this->assertStringContainsString('phase25-archive', $contents);
        $this->assertStringNotContainsString(self::RESET_TOKEN, $contents);
        $this->assertSame(0, JobLog::query()->count());
    }

    public function test_scrub_migration_clears_historical_payloads(): void
    {
        JobLog::query()->create([
            'job_uuid' => 'phase25-legacy',
            'job_name' => 'Legacy',
            'queue' => 'default',
            'status' => 'completed',
            'payload' => ['data' => ['command' => self::RESET_TOKEN]],
        ]);

        $migration = require database_path('migrations/2026_10_14_120000_scrub_job_logs_payload.php');
        $migration->up();

        $this->assertNull(DB::table('job_logs')->where('job_uuid', 'phase25-legacy')->value('payload'));
    }

    public function test_newsletter_delivery_failure_log_omits_subscriber_email(): void
    {
        \App\Models\NewsletterSubscriber::query()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'email' => 'reader@example.com', 'is_active' => true]);
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->andThrow(new \RuntimeException('550 rejected reader@example.com'));
        \Illuminate\Support\Facades\Log::spy();

        (new \App\Jobs\Email\SendNewsletterAnnouncementEmailsJob('Title', 'Excerpt', 'slug'))->handle();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => $message === 'Failed to send newsletter announcement email'
                && ! str_contains(json_encode($context), 'reader@example.com')
        )->once();
    }

    public function test_queue_health_metrics_cover_ems_queues(): void
    {
        JobLog::query()->create([
            'job_uuid' => 'phase25-ems',
            'job_name' => 'SendEventNotificationJob',
            'queue' => 'ems-notifications',
            'status' => 'failed',
        ]);

        $metrics = app(JobMonitoringService::class)->getQueueHealthMetrics();

        $this->assertSame(1, $metrics['queue_breakdown']['ems-notifications']['failed_count']);
        $this->assertCount(1, $metrics['hourly_throughput']);
    }
}
