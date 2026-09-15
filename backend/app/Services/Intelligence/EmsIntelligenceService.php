<?php

namespace App\Services\Intelligence;

use App\Ems\Models\CheckIn;
use App\Ems\Models\Event as EmsEvent;
use App\Ems\Models\EventFeedback;
use App\Ems\Models\EventVolunteer;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmsIntelligenceService
{
    /**
     * Resolve date bounds for current and previous comparison periods.
     */
    public function resolveDateBounds(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                $currentStart = $now->copy()->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $currentStart->copy()->subDay();
                $previousEnd = $currentStart->copy()->subSecond();
                break;
            case '7d':
                $currentStart = $now->copy()->subDays(6)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $currentStart->copy()->subDays(7);
                $previousEnd = $currentStart->copy()->subSecond();
                break;
            case '90d':
                $currentStart = $now->copy()->subDays(89)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $currentStart->copy()->subDays(90);
                $previousEnd = $currentStart->copy()->subSecond();
                break;
            case 'this_year':
                $currentStart = $now->copy()->startOfYear();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $currentStart->copy()->subYear();
                $previousEnd = $currentStart->copy()->subSecond();
                break;
            case 'custom':
                $currentStart = $startDate ? Carbon::parse($startDate)->startOfDay() : $now->copy()->subDays(29)->startOfDay();
                $currentEnd = $endDate ? Carbon::parse($endDate)->endOfDay() : $now->copy()->endOfDay();
                $diffInDays = max(1, $currentStart->diffInDays($currentEnd));
                $previousStart = $currentStart->copy()->subDays($diffInDays);
                $previousEnd = $currentStart->copy()->subSecond();
                break;
            case '30d':
            default:
                $currentStart = $now->copy()->subDays(29)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $currentStart->copy()->subDays(30);
                $previousEnd = $currentStart->copy()->subSecond();
                break;
        }

        return [
            'current' => ['start' => $currentStart, 'end' => $currentEnd],
            'previous' => ['start' => $previousStart, 'end' => $previousEnd],
        ];
    }

    public function calculatePctChange(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get detailed EMS operational analytics.
     */
    public function getAnalytics(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $bounds = $this->resolveDateBounds($period, $startDate, $endDate);
        $cStart = $bounds['current']['start'];
        $cEnd = $bounds['current']['end'];
        $pStart = $bounds['previous']['start'];
        $pEnd = $bounds['previous']['end'];

        if (! Schema::hasTable('ems_events')) {
            return $this->emptyEmsAnalytics();
        }

        // 1. Events KPI
        $currEvents = EmsEvent::whereBetween('created_at', [$cStart, $cEnd])->count();
        $prevEvents = EmsEvent::whereBetween('created_at', [$pStart, $pEnd])->count();
        $totalEvents = EmsEvent::count();

        // 2. Registrations KPI & Funnel
        $currRegistrations = Registration::whereBetween('created_at', [$cStart, $cEnd])->where('status', '!=', 'cancelled')->count();
        $prevRegistrations = Registration::whereBetween('created_at', [$pStart, $pEnd])->where('status', '!=', 'cancelled')->count();
        $totalRegistrations = Registration::where('status', '!=', 'cancelled')->count();

        $registeredCount = Registration::whereBetween('created_at', [$cStart, $cEnd])->count();
        $paidRegCount = Registration::whereBetween('created_at', [$cStart, $cEnd])
            ->whereHas('payments', function ($q) {
                $q->where('status', 'paid');
            })->count();

        $ticketsIssued = Ticket::whereBetween('created_at', [$cStart, $cEnd])->count();
        $checkInsCount = CheckIn::whereBetween('created_at', [$cStart, $cEnd])->count();
        $feedbackCount = EventFeedback::whereBetween('created_at', [$cStart, $cEnd])->count();

        // Attendance & No-show rates
        $attendanceRate = $ticketsIssued > 0 ? round(($checkInsCount / $ticketsIssued) * 100, 1) : 0.0;
        $noShowRate = $ticketsIssued > 0 ? round(((max(0, $ticketsIssued - $checkInsCount)) / $ticketsIssued) * 100, 1) : 0.0;

        // 3. Financial Analytics Breakdown
        $currRevenueCents = Payment::whereBetween('created_at', [$cStart, $cEnd])
            ->where('status', 'paid')
            ->sum('amount');
        $prevRevenueCents = Payment::whereBetween('created_at', [$pStart, $pEnd])
            ->where('status', 'paid')
            ->sum('amount');

        // Handles whether amount is in dollars or cents safely
        $currRevenue = (float) ($currRevenueCents > 100000 ? $currRevenueCents / 100 : $currRevenueCents);
        $prevRevenue = (float) ($prevRevenueCents > 100000 ? $prevRevenueCents / 100 : $prevRevenueCents);

        // Payment Method Breakdown
        $paymentMethodsRaw = Payment::whereBetween('created_at', [$cStart, $cEnd])
            ->where('status', 'paid')
            ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get();

        $onlineRevenue = 0.0;
        $manualOverrideRevenue = 0.0;
        $squarePosRevenue = 0.0;

        $methodsBreakdown = [];
        foreach ($paymentMethodsRaw as $row) {
            $amt = (float) ($row->total_amount > 100000 ? $row->total_amount / 100 : $row->total_amount);
            $methodsBreakdown[$row->payment_method] = [
                'count' => (int) $row->count,
                'amount' => round($amt, 2),
            ];

            if (in_array($row->payment_method, ['square', 'credit_card', 'stripe', 'online'])) {
                $onlineRevenue += $amt;
            } elseif (in_array($row->payment_method, ['square_pos', 'external_square'])) {
                $squarePosRevenue += $amt;
            } else {
                $manualOverrideRevenue += $amt;
            }
        }

        $refundsCount = Payment::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'refunded')->count();

        // 4. Upcoming & Top Events
        $upcomingEvents = EmsEvent::where('start_date', '>=', now())
            ->where('status', 'published')
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get(['id', 'uuid', 'name', 'start_date', 'capacity', 'status']);

        // 5. Daily Trend Data for Current Period
        $trends = $this->buildRegistrationTrend($cStart, $cEnd);

        return [
            'period' => [
                'type' => $period,
                'current_start' => $cStart->toIso8601String(),
                'current_end' => $cEnd->toIso8601String(),
                'previous_start' => $pStart->toIso8601String(),
                'previous_end' => $pEnd->toIso8601String(),
            ],
            'kpis' => [
                'events' => [
                    'current' => $currEvents,
                    'previous' => $prevEvents,
                    'total' => $totalEvents,
                    'pct_change' => $this->calculatePctChange($currEvents, $prevEvents),
                ],
                'registrations' => [
                    'current' => $currRegistrations,
                    'previous' => $prevRegistrations,
                    'total' => $totalRegistrations,
                    'pct_change' => $this->calculatePctChange($currRegistrations, $prevRegistrations),
                ],
                'revenue' => [
                    'current' => round($currRevenue, 2),
                    'previous' => round($prevRevenue, 2),
                    'pct_change' => $this->calculatePctChange($currRevenue, $prevRevenue),
                ],
                'attendance_rate' => $attendanceRate,
                'no_show_rate' => $noShowRate,
            ],
            'funnel' => [
                'registered' => $registeredCount,
                'paid' => $paidRegCount,
                'tickets_issued' => $ticketsIssued,
                'check_ins' => $checkInsCount,
                'feedback' => $feedbackCount,
            ],
            'financial' => [
                'total_revenue' => round($currRevenue, 2),
                'online_revenue' => round($onlineRevenue, 2),
                'square_pos_revenue' => round($squarePosRevenue, 2),
                'manual_override_revenue' => round($manualOverrideRevenue, 2),
                'refunds_count' => $refundsCount,
                'methods_breakdown' => $methodsBreakdown,
            ],
            'upcoming_events' => $upcomingEvents,
            'trends' => $trends,
        ];
    }

    private function buildRegistrationTrend(Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateStr = $cursor->format('Y-m-d');
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $count = Registration::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', '!=', 'cancelled')
                ->count();

            $revCents = Payment::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'paid')
                ->sum('amount');
            $rev = (float) ($revCents > 100000 ? $revCents / 100 : $revCents);

            $days[] = [
                'date' => $dateStr,
                'label' => $cursor->format('M j'),
                'registrations' => $count,
                'revenue' => round($rev, 2),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    private function emptyEmsAnalytics(): array
    {
        return [
            'period' => ['type' => '30d'],
            'kpis' => [
                'events' => ['current' => 0, 'previous' => 0, 'total' => 0, 'pct_change' => 0.0],
                'registrations' => ['current' => 0, 'previous' => 0, 'total' => 0, 'pct_change' => 0.0],
                'revenue' => ['current' => 0.0, 'previous' => 0.0, 'pct_change' => 0.0],
                'attendance_rate' => 0.0,
                'no_show_rate' => 0.0,
            ],
            'funnel' => ['registered' => 0, 'paid' => 0, 'tickets_issued' => 0, 'check_ins' => 0, 'feedback' => 0],
            'financial' => ['total_revenue' => 0.0, 'online_revenue' => 0.0, 'square_pos_revenue' => 0.0, 'manual_override_revenue' => 0.0, 'refunds_count' => 0, 'methods_breakdown' => []],
            'upcoming_events' => [],
            'trends' => [],
        ];
    }
}
