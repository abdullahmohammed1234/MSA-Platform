<?php

namespace App\Services\Lifecycle;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SchedulerQueueInventoryService
{
    private const CRON_HEARTBEAT_KEY = 'platform.cron.last_heartbeat';

    /**
     * Get complete scheduler and queue inventory.
     */
    public function getSchedulerQueueInventory(): array
    {
        $scheduler = $this->getSchedulerDetails();
        $queues = $this->getQueueDetails();

        return [
            'scheduler' => $scheduler,
            'queues' => $queues,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }

    private function getSchedulerDetails(): array
    {
        $lastHeartbeat = Cache::get(self::CRON_HEARTBEAT_KEY);
        $minutesAgo = $lastHeartbeat ? Carbon::parse($lastHeartbeat)->diffInMinutes(now()) : null;

        $isVerified = $minutesAgo !== null && $minutesAgo <= 15;

        // Registered operational tasks
        $definedTasks = [
            [
                'command' => 'operations:detect',
                'description' => 'Run operational alert detection rules across all domain engines',
                'expression' => '*/5 * * * *',
                'status' => $isVerified ? 'VERIFIED' : 'EXPECTED',
            ],
            [
                'command' => 'governance:continuity-check',
                'description' => 'Run operational continuity & infrastructure probes',
                'expression' => '0 * * * *',
                'status' => $isVerified ? 'VERIFIED' : 'DEFINED',
            ],
            [
                'command' => 'intelligence:aggregate',
                'description' => 'Aggregate platform intelligence metrics across EMS, Store, Donations, MLibMS',
                'expression' => '0 0 * * *',
                'status' => 'DEFINED',
            ],
        ];

        return [
            'driver' => 'cPanel Cron / Artisan Schedule',
            'status' => $isVerified ? 'VERIFIED' : ($lastHeartbeat ? 'OBSERVED' : 'NOT_VERIFIED'),
            'last_heartbeat' => $lastHeartbeat,
            'minutes_since_heartbeat' => $minutesAgo,
            'defined_tasks_count' => count($definedTasks),
            'tasks' => $definedTasks,
            'execution_semantics' => [
                'defined' => 'Task is registered in Laravel Schedule Kernel.',
                'expected' => 'Task is required for operational automation heartbeat.',
                'verified' => 'Recent cron execution evidence recorded in application cache.',
                'unknown' => 'External cron runner status cannot be directly polled under cPanel.',
            ],
        ];
    }

    private function getQueueDetails(): array
    {
        $driver = config('queue.default', 'sync');
        $backlogCount = 0;
        $failedJobsCount = 0;

        try {
            if ($driver === 'database' && Schema::hasTable('jobs')) {
                $backlogCount = DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            // Keep safe fallbacks
        }

        $verificationState = match (true) {
            $failedJobsCount > 50 => 'DEGRADED',
            $driver === 'sync' => 'CONFIGURED_SYNC',
            $backlogCount > 0 => 'OBSERVED_BACKLOG',
            default => 'CONFIGURED',
        };

        return [
            'driver' => $driver,
            'verification_state' => $verificationState,
            'configured_queues' => ['default', 'notifications', 'remediation', 'reports'],
            'backlog_count' => $backlogCount,
            'failed_jobs_count' => $failedJobsCount,
            'worker_expectation' => $driver === 'sync'
                ? 'Jobs executed synchronously in-request.'
                : 'Jobs processed via background artisan queue:work or cPanel cron runner.',
        ];
    }
}
