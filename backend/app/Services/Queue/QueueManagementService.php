<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class QueueManagementService
{
    /**
     * Get overview status of all configured queues.
     */
    public function getQueueStatus(): array
    {
        $queues = ['high', 'default', 'low'];
        $status = [];

        foreach ($queues as $qName) {
            $pending = DB::table('jobs')
                ->where('queue', $qName)
                ->whereNull('reserved_at')
                ->count();

            $active = DB::table('jobs')
                ->where('queue', $qName)
                ->whereNotNull('reserved_at')
                ->count();

            $failed = DB::table('failed_jobs')
                ->where('queue', $qName)
                ->count();

            $status[] = [
                'name' => $qName,
                'pending_count' => $pending,
                'active_count' => $active,
                'failed_count' => $failed,
                'status' => $active > 0 ? 'active' : ($pending > 0 ? 'idle' : 'empty'),
            ];
        }

        return $status;
    }

    /**
     * Get failed jobs list with pagination.
     */
    public function getFailedJobs(int $perPage = 15): array
    {
        $paginator = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $displayName = $payload['displayName'] ?? 'Unknown Job';
            
            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'name' => $displayName,
                'exception' => $job->exception,
                'failed_at' => $job->failed_at,
            ];
        });

        return [
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Retry a failed job by UUID or ID.
     */
    public function retryJob(string $uuid): bool
    {
        Log::info("Retrying failed job: {$uuid}");

        // Using Artisan command allows standard Laravel retry logic
        $exitCode = Artisan::call('queue:retry', [
            'id' => [$uuid]
        ]);

        return $exitCode === 0;
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllJobs(): bool
    {
        Log::info("Retrying all failed jobs...");

        $exitCode = Artisan::call('queue:retry', [
            'id' => ['all']
        ]);

        return $exitCode === 0;
    }

    /**
     * Delete a failed job by UUID.
     */
    public function deleteFailedJob(string $uuid): bool
    {
        Log::info("Deleting failed job: {$uuid}");

        $deleted = DB::table('failed_jobs')
            ->where('uuid', $uuid)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Delete all failed jobs.
     */
    public function deleteAllFailedJobs(): bool
    {
        Log::info("Clearing all failed jobs from DB.");

        DB::table('failed_jobs')->truncate();

        return true;
    }

    /**
     * Clear all pending jobs in a queue.
     */
    public function clearQueue(string $queueName): bool
    {
        Log::info("Clearing queue: {$queueName}");

        DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNull('reserved_at')
            ->delete();

        return true;
    }
}
