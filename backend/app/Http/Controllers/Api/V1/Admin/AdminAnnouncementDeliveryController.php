<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Models\CMS\Announcement;
use App\Models\Notification;
use App\Services\Announcements\AnnouncementDeliveryService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Delivery visibility and intentional recovery for announcement sends. Responses
 * carry recipient ids only, never email addresses.
 */
class AdminAnnouncementDeliveryController extends Controller
{
    public function __construct(private readonly AnnouncementDeliveryService $deliveries)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', Notification::class);

        $page = AnnouncementDispatch::query()
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $counts = $this->statusCounts($page->getCollection()->pluck('id')->all());

        return response()->json([
            'success' => true,
            'data' => $page->getCollection()->map(fn (AnnouncementDispatch $d) => $this->summary($d, $counts[$d->id] ?? [])),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(AnnouncementDispatch $dispatch): JsonResponse
    {
        $this->authorize('manage', Notification::class);

        $failed = AnnouncementDelivery::query()
            ->where('dispatch_id', $dispatch->id)
            ->whereIn('status', [AnnouncementDelivery::STATUS_FAILED, AnnouncementDelivery::STATUS_PERMANENTLY_FAILED])
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'recipient_type', 'recipient_id', 'channel', 'status', 'attempts', 'manual_retries', 'next_attempt_at', 'failed_at', 'last_error']);

        return response()->json([
            'success' => true,
            'data' => $this->summary($dispatch, $this->statusCounts([$dispatch->id])[$dispatch->id] ?? []) + [
                'failed_deliveries' => $failed,
            ],
        ]);
    }

    public function retryFailed(Request $request, AnnouncementDispatch $dispatch): JsonResponse
    {
        $this->authorize('send', Notification::class);

        $count = $this->deliveries->retryPermanentFailures($dispatch, $request->user()?->id);

        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'There are no permanently failed deliveries to retry.',
            ], 409);
        }

        AuditLogger::log('announcement.delivery.manual_retry', $dispatch, 'Retried permanently failed announcement deliveries.', [
            'dispatch_uuid' => $dispatch->uuid,
            'rows' => $count,
        ], $request->user()?->id, 'platform');

        return response()->json([
            'success' => true,
            'message' => "{$count} failed deliveries queued for retry.",
            'data' => ['retried' => $count],
        ]);
    }

    public function resend(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('send', Notification::class);

        if ($announcement->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Only a published announcement can be resent.',
            ], 422);
        }

        $dispatch = $this->deliveries->resend($announcement, $request->user()?->id);

        if ($dispatch === null) {
            return response()->json([
                'success' => false,
                'message' => 'A resend of this announcement is already in progress.',
            ], 409);
        }

        AuditLogger::log('announcement.resend', $announcement, 'Intentionally resent an announcement to all current recipients.', [
            'dispatch_uuid' => $dispatch->uuid,
        ], $request->user()?->id, 'platform');

        return response()->json([
            'success' => true,
            'message' => 'Announcement resend queued.',
            'data' => ['dispatch_uuid' => $dispatch->uuid],
        ], 202);
    }

    /**
     * @param  list<int>  $dispatchIds
     * @return array<int, array<string, int>>
     */
    private function statusCounts(array $dispatchIds): array
    {
        if ($dispatchIds === []) {
            return [];
        }

        $counts = [];
        AnnouncementDelivery::query()
            ->whereIn('dispatch_id', $dispatchIds)
            ->selectRaw('dispatch_id, status, COUNT(*) as aggregate')
            ->groupBy('dispatch_id', 'status')
            ->get()
            ->each(function ($row) use (&$counts): void {
                $counts[(int) $row->dispatch_id][(string) $row->status] = (int) $row->aggregate;
            });

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function summary(AnnouncementDispatch $dispatch, array $counts): array
    {
        return [
            'uuid' => $dispatch->uuid,
            'kind' => $dispatch->kind,
            'announcement_id' => $dispatch->announcement_id,
            'audience' => $dispatch->audience,
            'title' => $dispatch->title,
            'status' => $dispatch->status,
            'recipients_total' => $dispatch->recipients_total,
            'counts' => $counts,
            'created_by' => $dispatch->created_by,
            'created_at' => $dispatch->created_at?->toIso8601String(),
            'prepared_at' => $dispatch->prepared_at?->toIso8601String(),
            'completed_at' => $dispatch->completed_at?->toIso8601String(),
        ];
    }
}
