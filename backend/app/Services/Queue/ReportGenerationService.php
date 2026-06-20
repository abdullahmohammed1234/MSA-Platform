<?php

namespace App\Services\Queue;

use App\Models\AnalyticsReport;
use App\Jobs\Analytics\GenerateScheduledReportsJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportGenerationService
{
    /**
     * Get paginated list of generated reports.
     */
    public function getReports(int $perPage = 10): array
    {
        $paginator = AnalyticsReport::with('generatedBy')
            ->orderBy('generated_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Request manual generation of a report.
     */
    public function triggerReportGeneration(string $period, ?int $userId = null): void
    {
        Log::info("Manually triggering report generation for period: {$period} by User: {$userId}");

        // Dispatch report job to low priority queue
        GenerateScheduledReportsJob::dispatch($period)->onQueue('low');
    }

    /**
     * Download a report file.
     */
    public function downloadReport(string $uuid): ?BinaryFileResponse
    {
        $report = AnalyticsReport::where('uuid', $uuid)->first();
        if (!$report) {
            return null;
        }

        $filePath = storage_path('app/' . $report->file_path);
        if (!file_exists($filePath)) {
            return null;
        }

        return response()->download($filePath, $report->title . '.pdf');
    }

    /**
     * Delete a report.
     */
    public function deleteReport(string $uuid): bool
    {
        $report = AnalyticsReport::where('uuid', $uuid)->first();
        if (!$report) {
            return false;
        }

        // Delete from disk
        $filePath = storage_path('app/' . $report->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from DB
        return $report->delete();
    }
}
