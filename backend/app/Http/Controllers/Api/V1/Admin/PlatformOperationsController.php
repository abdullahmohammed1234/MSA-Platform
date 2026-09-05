<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Queue\QueueManagementService;
use App\Services\Queue\SchedulerService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformOperationsController extends Controller
{
    public function __construct(
        private QueueManagementService $queueService,
        private SchedulerService $schedulerService,
        private AuditLogger $auditLogger
    ) {}

    /**
     * POST /api/v1/admin/platform/operations/retry-job
     */
    public function retryJob(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('retry_failed_jobs') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'uuid' => 'nullable|string',
            'job_id' => 'nullable',
        ]);

        $jobId = $validated['uuid'] ?? (string) ($validated['job_id'] ?? '');

        $success = $this->queueService->retryJob($jobId);

        if ($success) {
            $this->auditLogger->log(
                action: 'retry_failed_job',
                description: "Retried failed background job: {$jobId}",
                payload: ['job_id' => $jobId],
                userId: $user->id,
                application: 'platform',
                severity: 'warning'
            );
        }

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Job queued for retry.' : 'Failed to retry job.',
        ]);
    }

    /**
     * POST /api/v1/admin/platform/operations/flush-failed
     * Destructive Action Guarded with confirm flag and audit log.
     */
    public function flushFailed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('manage_queues') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.operations'], 403);
        }

        $validated = $request->validate([
            'confirm' => 'required|boolean',
            'scope' => 'nullable|string|in:all,specific',
            'uuid' => 'required_if:scope,specific|nullable|string',
        ]);

        if (! $validated['confirm']) {
            return response()->json([
                'success' => false,
                'message' => 'Destructive operation rejected. Confirmation payload (confirm: true) is required.',
            ], 422);
        }

        $countBefore = DB::table('failed_jobs')->count();

        if (($validated['scope'] ?? 'all') === 'specific' && ! empty($validated['uuid'])) {
            $success = $this->queueService->deleteFailedJob($validated['uuid']);
            $actionDesc = "Flushed specific failed job: {$validated['uuid']}";
            $flushedCount = $success ? 1 : 0;
        } else {
            $success = $this->queueService->deleteAllFailedJobs();
            $actionDesc = "Flushed all {$countBefore} failed background jobs";
            $flushedCount = $countBefore;
        }

        $this->auditLogger->log(
            action: 'flush_failed_jobs',
            description: $actionDesc,
            payload: [
                'scope' => $validated['scope'] ?? 'all',
                'flushed_count' => $flushedCount,
                'initiated_by_user_id' => $user->id,
            ],
            userId: $user->id,
            application: 'platform',
            severity: 'critical'
        );

        return response()->json([
            'success' => $success,
            'message' => $actionDesc,
            'flushed_count' => $flushedCount,
        ]);
    }

    /**
     * POST /api/v1/admin/platform/operations/run-task
     */
    public function runTask(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('manage_scheduler') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'task_id' => 'required|integer',
        ]);

        $success = $this->schedulerService->runScheduledTask($validated['task_id']);

        if ($success) {
            $this->auditLogger->log(
                action: 'run_scheduled_task',
                description: "Manually triggered scheduled cron task ID: {$validated['task_id']}",
                payload: ['task_id' => $validated['task_id']],
                userId: $user->id,
                application: 'platform',
                severity: 'info'
            );
        }

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Scheduled task executed.' : 'Failed to execute scheduled task.',
        ]);
    }
}
