<?php

namespace App\Services\Intelligence;

use App\Ems\Models\EventNotification;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Schema;

class CommunicationsIntelligenceService
{
    private EmsIntelligenceService $helper;

    public function __construct(EmsIntelligenceService $helper)
    {
        $this->helper = $helper;
    }

    public function getAnalytics(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $bounds = $this->helper->resolveDateBounds($period, $startDate, $endDate);
        $cStart = $bounds['current']['start'];
        $cEnd = $bounds['current']['end'];
        $pStart = $bounds['previous']['start'];
        $pEnd = $bounds['previous']['end'];

        $sentCount = 0;
        $deliveredCount = 0;
        $failedCount = 0;
        $queuedCount = 0;

        if (Schema::hasTable('notification_logs')) {
            $sentCount += NotificationLog::whereBetween('created_at', [$cStart, $cEnd])->whereIn('status', ['sent', 'delivered'])->count();
            $deliveredCount += NotificationLog::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'delivered')->count();
            $failedCount += NotificationLog::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'failed')->count();
            $queuedCount += NotificationLog::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'queued')->count();
        }

        if (Schema::hasTable('ems_notifications')) {
            $sentCount += EventNotification::whereBetween('created_at', [$cStart, $cEnd])->whereIn('status', ['sent', 'delivered'])->count();
            $deliveredCount += EventNotification::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'delivered')->count();
            $failedCount += EventNotification::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'failed')->count();
            $queuedCount += EventNotification::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'queued')->count();
        }

        $totalDispatched = $sentCount + $deliveredCount + $failedCount + $queuedCount;
        $deliveryRate = $totalDispatched > 0 ? round((($sentCount + $deliveredCount) / $totalDispatched) * 100, 1) : 100.0;
        $failureRate = $totalDispatched > 0 ? round(($failedCount / $totalDispatched) * 100, 1) : 0.0;

        // Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $daySent = 0;
            $dayFailed = 0;

            if (Schema::hasTable('notification_logs')) {
                $daySent += NotificationLog::whereBetween('created_at', [$dayStart, $dayEnd])->whereIn('status', ['sent', 'delivered'])->count();
                $dayFailed += NotificationLog::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', 'failed')->count();
            }

            if (Schema::hasTable('ems_notifications')) {
                $daySent += EventNotification::whereBetween('created_at', [$dayStart, $dayEnd])->whereIn('status', ['sent', 'delivered'])->count();
                $dayFailed += EventNotification::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', 'failed')->count();
            }

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'sent' => $daySent,
                'failed' => $dayFailed,
            ];

            $cursor->addDay();
        }

        return [
            'period' => [
                'type' => $period,
                'current_start' => $cStart->toIso8601String(),
                'current_end' => $cEnd->toIso8601String(),
            ],
            'kpis' => [
                'total_dispatched' => $totalDispatched,
                'sent' => $sentCount,
                'delivered' => $deliveredCount,
                'failed' => $failedCount,
                'queued' => $queuedCount,
                'delivery_rate' => $deliveryRate,
                'failure_rate' => $failureRate,
            ],
            'trends' => $trends,
        ];
    }
}
