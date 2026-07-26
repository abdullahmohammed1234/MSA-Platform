<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Models\AnalyticsReport;
use App\Ems\Support\EmsPermissions;
use App\Ems\Services\AnalyticsService;
use App\Ems\Services\EmsActivityLogger;
use App\Ems\Jobs\GenerateReportJob;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnalyticsController extends EmsController
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly EmsActivityLogger $logger
    ) {
    }

    /**
     * GET /api/v1/ems/analytics/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW)) {
            $this->logger->denied('analytics.dashboard_view', null, 'Permission denied to view general analytics dashboard.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->validate([
            'event_uuid' => 'nullable|uuid',
            'category_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $payload = $this->analytics->getDashboardPayload($request->user(), $filters);

        // Strip financial data if user doesn't have permissions
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL)) {
            $payload['kpis']['gross_revenue'] = 0.0;
            $payload['kpis']['refunds'] = 0.0;
            $payload['kpis']['net_revenue'] = 0.0;
            if (isset($payload['charts']['ticket_performance'])) {
                foreach ($payload['charts']['ticket_performance'] as &$perf) {
                    $perf['revenue'] = 0.0;
                }
            }
            if (isset($payload['charts']['early_bird']['comparison'])) {
                foreach ($payload['charts']['early_bird']['comparison'] as &$comp) {
                    $comp['revenue'] = 0.0;
                }
            }
        }

        $this->logger->log('analytics.dashboard_view', null, 'User viewed the general analytics dashboard.', $filters);

        return ApiResponse::success($payload, 'General analytics retrieved successfully.');
    }

    /**
     * GET /api/v1/ems/analytics/advanced-report
     */
    public function advancedReport(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW)) {
            $this->logger->denied('analytics.advanced_report_view', null, 'Permission denied to view advanced report.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->validate([
            'event_uuid' => 'nullable|uuid',
            'category_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'organizer_id' => 'nullable|integer',
            'series_id' => 'nullable|integer',
        ]);

        $payload = $this->analytics->getAdvancedReportPayload($request->user(), $filters);

        // Strip financial data if user doesn't have permissions
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL)) {
            if (isset($payload['organizers'])) {
                foreach ($payload['organizers'] as &$org) {
                    $org['revenue_generated'] = 0.0;
                }
            }
            if (isset($payload['categories'])) {
                foreach ($payload['categories'] as &$cat) {
                    $cat['revenue_generated'] = 0.0;
                }
            }
            if (isset($payload['trends'])) {
                foreach ($payload['trends'] as &$trend) {
                    $trend['revenue'] = 0.0;
                }
            }
        }

        $this->logger->log('analytics.advanced_report_view', null, 'User viewed advanced reports.', $filters);

        return ApiResponse::success($payload, 'Advanced analytics report retrieved successfully.');
    }


    /**
     * GET /api/v1/ems/events/{event}/analytics
     */
    public function analytics(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW)) {
            $this->logger->denied('analytics.view', $event, 'Permission denied to view event analytics.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $payload = $this->analytics->getDashboardPayload($request->user(), ['event_uuid' => $event->uuid]);

        // Strip financial data if user doesn't have permissions
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL)) {
            $payload['kpis']['gross_revenue'] = 0.0;
            $payload['kpis']['refunds'] = 0.0;
            $payload['kpis']['net_revenue'] = 0.0;
            if (isset($payload['charts']['ticket_performance'])) {
                foreach ($payload['charts']['ticket_performance'] as &$perf) {
                    $perf['revenue'] = 0.0;
                }
            }
            if (isset($payload['charts']['early_bird']['comparison'])) {
                foreach ($payload['charts']['early_bird']['comparison'] as &$comp) {
                    $comp['revenue'] = 0.0;
                }
            }
        }

        $this->logger->log('analytics.view', $event, "User viewed event analytics for '{$event->name}'.");

        return ApiResponse::success($payload, 'Event analytics retrieved successfully.');
    }

    /**
     * GET /api/v1/ems/events/{event}/attendance
     */
    public function attendance(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW)) {
            $this->logger->denied('analytics.attendance', $event, 'Permission denied to view event attendance.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $payload = $this->analytics->getDashboardPayload($request->user(), ['event_uuid' => $event->uuid]);

        $attendanceMetrics = [
            'total_registrations' => $payload['kpis']['total_registrations'],
            'confirmed_registrations' => $payload['kpis']['confirmed_registrations'],
            'tickets_issued' => $payload['kpis']['tickets_issued'],
            'checked_in' => $payload['kpis']['checked_in'],
            'no_shows' => $payload['kpis']['no_shows'],
            'attendance_rate' => $payload['kpis']['attendance_rate'],
            'no_show_rate' => $payload['kpis']['no_show_rate'],
            'capacity_utilization' => $payload['kpis']['capacity_utilization'],
        ];

        $this->logger->log('analytics.attendance', $event, "User viewed attendance analytics for '{$event->name}'.");

        return ApiResponse::success($attendanceMetrics, 'Event attendance analytics retrieved successfully.');
    }

    /**
     * GET /api/v1/ems/events/{event}/revenue
     */
    public function revenue(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL)) {
            $this->logger->denied('analytics.revenue', $event, 'Permission denied to view event financial analytics.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $payload = $this->analytics->getDashboardPayload($request->user(), ['event_uuid' => $event->uuid]);

        $revenueMetrics = [
            'gross_revenue' => $payload['kpis']['gross_revenue'],
            'refunds' => $payload['kpis']['refunds'],
            'net_revenue' => $payload['kpis']['net_revenue'],
            'tickets_sold' => $payload['kpis']['tickets_sold'],
            'average_ticket_value' => $payload['kpis']['net_revenue'] / max(1, $payload['kpis']['tickets_sold']),
            'ticket_performance' => $payload['charts']['ticket_performance'],
            'early_bird' => $payload['charts']['early_bird'],
        ];

        $this->logger->log('analytics.revenue', $event, "User viewed financial analytics for '{$event->name}'.");

        return ApiResponse::success($revenueMetrics, 'Event financial analytics retrieved successfully.');
    }

    /**
     * GET /api/v1/ems/analytics/compare
     */
    public function compare(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW)) {
            $this->logger->denied('analytics.compare', null, 'Permission denied to perform event comparison.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'event_uuids' => 'required|array',
            'event_uuids.*' => 'uuid',
        ]);

        $comparison = $this->analytics->getEventComparison($request->user(), $validated['event_uuids']);

        // Strip financial data if user doesn't have permissions
        if (!$request->user()->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL)) {
            foreach ($comparison as &$item) {
                $item['gross_revenue'] = 0.0;
                $item['refunds'] = 0.0;
                $item['net_revenue'] = 0.0;
            }
        }

        $this->logger->log('analytics.compare', null, 'User performed side-by-side event comparison.', $validated);

        return ApiResponse::success($comparison, 'Event comparison metrics retrieved successfully.');
    }

    /**
     * GET /api/v1/ems/events/{event}/reports
     */
    public function reports(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (!$request->user()->hasPermission(EmsPermissions::REPORTS_MANAGE)) {
            $this->logger->denied('reports.view', $event, 'Permission denied to list event reports.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $reports = AnalyticsReport::where('type', 'ems')
            ->where(function($q) use ($event) {
                $q->whereJsonContains('filters->event_uuid', $event->uuid);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($reports, 'Event reports retrieved successfully.');
    }

    /**
     * POST /api/v1/ems/events/{event}/reports/export
     */
    public function export(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (!$request->user()->hasPermission(EmsPermissions::REPORTS_MANAGE)) {
            $this->logger->denied('reports.export', $event, 'Permission denied to generate report.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'format' => 'required|string|in:csv,xlsx,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'sections' => 'required|array',
            'sections.registrations' => 'boolean',
            'sections.revenue' => 'boolean',
            'sections.attendance' => 'boolean',
            'sections.ticket_sales' => 'boolean',
            'sections.payments' => 'boolean',
            'sections.waitlist' => 'boolean',
            'sections.check_ins' => 'boolean',
        ]);

        try {
            // Create analytics report record
            $report = AnalyticsReport::create([
                'uuid' => (string) Str::uuid(),
                'title' => $validated['title'],
                'type' => 'ems',
                'filters' => array_merge($validated, ['event_uuid' => $event->uuid]),
                'generated_by' => $request->user()->id,
                'file_path' => null, // filled in by the background job
            ]);

            // Dispatch background job
            GenerateReportJob::dispatch($report->id, $event->uuid);

            $this->logger->log('reports.export', $event, "Queued export for report '{$report->title}' in format '{$validated['format']}'.");

            return ApiResponse::success($report, 'Report generation queued successfully.');
        } catch (\Exception $e) {
            $this->logger->failed('reports.export', $event, 'Failed to queue report export: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to queue report export.'], 500);
        }
    }

    /**
     * GET /api/v1/ems/reports/{report}/download
     */
    public function download(Request $request, string $uuid)
    {
        $report = AnalyticsReport::where('uuid', $uuid)->firstOrFail();

        // Enforce system read permission or report ownership
        if (!$request->user()->hasPermission(EmsPermissions::REPORTS_MANAGE)) {
            $this->logger->denied('reports.download', null, 'Permission denied to download report.');
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (!$report->file_path || !Storage::disk('local')->exists($report->file_path)) {
            $this->logger->failed('reports.download', null, "Report file '{$report->file_path}' not found on storage.");
            abort(404, 'Report file not found.');
        }

        $this->logger->log('reports.download', null, "Downloaded report '{$report->title}'.");

        return Storage::disk('local')->download($report->file_path, basename($report->file_path));
    }
}
