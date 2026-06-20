<?php

namespace App\Providers;

use App\Models\JobLog;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event) {
            $uuid = $event->job->uuid();
            if (!$uuid) {
                return;
            }

            JobLog::updateOrCreate(
                ['job_uuid' => $uuid],
                [
                    'job_name' => method_exists($event->job, 'resolveName') ? $event->job->resolveName() : (method_exists($event->job, 'displayName') ? $event->job->displayName() : get_class($event->job)),
                    'queue' => $event->job->getQueue(),
                    'status' => 'processing',
                    'started_at' => now(),
                    'attempts' => $event->job->attempts(),
                    'payload' => $event->job->payload(),
                ]
            );
        });

        Queue::after(function (JobProcessed $event) {
            $uuid = $event->job->uuid();
            if (!$uuid) {
                return;
            }

            $log = JobLog::where('job_uuid', $uuid)->first();
            if ($log) {
                $completedAt = now();
                $duration = $log->started_at ? $completedAt->diffInSeconds($log->started_at, true) : 0;
                $log->update([
                    'status' => 'completed',
                    'completed_at' => $completedAt,
                    'duration' => $duration,
                ]);
            }
        });

        Queue::failing(function (JobFailed $event) {
            $uuid = $event->job->uuid();
            if (!$uuid) {
                return;
            }

            $log = JobLog::where('job_uuid', $uuid)->first();
            if ($log) {
                $completedAt = now();
                $duration = $log->started_at ? $completedAt->diffInSeconds($log->started_at, true) : 0;
                $log->update([
                    'status' => 'failed',
                    'completed_at' => $completedAt,
                    'duration' => $duration,
                    'failure_reason' => $event->exception ? $event->exception->getMessage() : 'Unknown error',
                ]);
            }
        });
    }
}
