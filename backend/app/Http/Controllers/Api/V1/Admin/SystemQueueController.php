<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Queue\QueueManagementService;
use App\Services\Queue\JobMonitoringService;
use App\Services\Queue\ReportGenerationService;
use App\Services\Queue\SchedulerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemQueueController extends Controller
{
    protected QueueManagementService $queueService;
    protected JobMonitoringService $monitoringService;
    protected ReportGenerationService $reportService;
    protected SchedulerService $schedulerService;

    public function __construct(
        QueueManagementService $queueService,
        JobMonitoringService $monitoringService,
        ReportGenerationService $reportService,
        SchedulerService $schedulerService
    ) {
        $this->queueService = $queueService;
        $this->monitoringService = $monitoringService;
        $this->reportService = $reportService;
        $this->schedulerService = $schedulerService;
    }

    /**
     * GET /api/v1/system/queues
     * Get queue partitions health/status.
     */
    public function queues(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_queue_status')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_queue_status'], 403);
        }

        $queues = $this->queueService->getQueueStatus();
        $metrics = $this->monitoringService->getQueueHealthMetrics();

        return response()->json([
            'success' => true,
            'queues' => $queues,
            'metrics' => $metrics,
        ]);
    }

    /**
     * GET /api/v1/system/jobs
     * Get job execution logs feed.
     */
    public function jobLogs(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_queue_status')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_queue_status'], 403);
        }

        $filters = $request->only(['queue', 'status', 'job_name']);
        $perPage = (int) $request->query('per_page', 15);

        $logs = $this->monitoringService->getJobLogs($filters, $perPage);

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * GET /api/v1/system/jobs/failed
     * List all failed jobs.
     */
    public function failedJobs(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_queue_status')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_queue_status'], 403);
        }

        $perPage = (int) $request->query('per_page', 15);
        $failed = $this->queueService->getFailedJobs($perPage);

        return response()->json([
            'success' => true,
            'failed_jobs' => $failed,
        ]);
    }

    /**
     * POST /api/v1/system/jobs/failed/{uuid}/retry
     * Retry a failed job.
     */
    public function retryFailedJob(Request $request, string $uuid): JsonResponse
    {
        if (!$request->user() || (!$request->user()->hasPermission('retry_failed_jobs') && !$request->user()->hasPermission('manage_queues'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: retry_failed_jobs or manage_queues'], 403);
        }

        $success = $this->queueService->retryJob($uuid);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Job dispatched for retry.' : 'Failed to retry job.',
        ]);
    }

    /**
     * POST /api/v1/system/jobs/failed/retry-all
     * Retry all failed jobs.
     */
    public function retryAllFailedJobs(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_queues')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_queues'], 403);
        }

        $success = $this->queueService->retryAllJobs();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'All failed jobs queued for retry.' : 'Failed to retry jobs.',
        ]);
    }

    /**
     * DELETE /api/v1/system/jobs/failed/{uuid}
     * Delete/forget a failed job.
     */
    public function deleteFailedJob(Request $request, string $uuid): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_queues')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_queues'], 403);
        }

        $success = $this->queueService->deleteFailedJob($uuid);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Failed job record deleted.' : 'Job not found.',
        ]);
    }

    /**
     * DELETE /api/v1/system/jobs/failed
     * Delete/flush all failed jobs.
     */
    public function deleteAllFailedJobs(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_queues')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_queues'], 403);
        }

        $success = $this->queueService->deleteAllFailedJobs();

        return response()->json([
            'success' => $success,
            'message' => 'All failed job records flushed.',
        ]);
    }

    /**
     * POST /api/v1/system/queues/clear
     * Clear all pending jobs in a queue partition.
     */
    public function clearQueue(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_queues')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_queues'], 403);
        }

        $validated = $request->validate([
            'queue' => 'required|string|in:high,default,low',
        ]);

        $success = $this->queueService->clearQueue($validated['queue']);

        return response()->json([
            'success' => $success,
            'message' => "Pending jobs in queue '{$validated['queue']}' cleared.",
        ]);
    }

    /**
     * GET /api/v1/system/reports
     * List generated reports.
     */
    public function reports(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_reports')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_reports'], 403);
        }

        $perPage = (int) $request->query('per_page', 10);
        $reports = $this->reportService->getReports($perPage);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * POST /api/v1/system/reports/generate
     * Manually trigger report generation.
     */
    public function generateReport(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_reports')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_reports'], 403);
        }

        $validated = $request->validate([
            'period' => 'required|string|in:daily,weekly,monthly',
        ]);

        $this->reportService->triggerReportGeneration(
            $validated['period'],
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => "Manual " . $validated['period'] . " report generation job has been queued in low-priority channel.",
        ]);
    }

    /**
     * GET /api/v1/system/reports/{uuid}/download
     * Download a report PDF.
     */
    public function downloadReport(Request $request, string $uuid)
    {
        if (!$request->user() || !$request->user()->hasPermission('view_reports')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_reports'], 403);
        }

        $response = $this->reportService->downloadReport($uuid);
        if (!$response) {
            return response()->json(['message' => 'Report not found or file missing on storage disk.'], 404);
        }

        return $response;
    }

    /**
     * DELETE /api/v1/system/reports/{uuid}
     * Delete report entry and file.
     */
    public function deleteReport(Request $request, string $uuid): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_reports')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_reports'], 403);
        }

        $success = $this->reportService->deleteReport($uuid);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Report deleted successfully.' : 'Failed to delete report.',
        ]);
    }

    /**
     * GET /api/v1/system/scheduler
     * List all scheduled tasks.
     */
    public function scheduler(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_scheduler')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_scheduler'], 403);
        }

        $tasks = $this->schedulerService->getScheduledTasks();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    }

    /**
     * POST /api/v1/system/scheduler/run
     * Run a scheduled task immediately.
     */
    public function runScheduledTask(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('manage_scheduler')) {
            return response()->json(['message' => 'Unauthorized. Required permission: manage_scheduler'], 403);
        }

        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        $success = $this->schedulerService->runScheduledTask($validated['id']);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Scheduled task triggered successfully.' : 'Scheduled task failed to run.',
        ]);
    }
}
