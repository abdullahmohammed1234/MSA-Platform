<?php

namespace App\Services\Intelligence;

use App\Ems\Models\EventVolunteer;
use App\Models\VolunteerRegistration;
use Illuminate\Support\Facades\Schema;

class VolunteerIntelligenceService
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

        $totalApplications = 0;
        $pendingCount = 0;
        $approvedCount = 0;
        $declinedCount = 0;

        if (Schema::hasTable('ems_event_volunteers')) {
            $totalApplications += EventVolunteer::whereBetween('created_at', [$cStart, $cEnd])->count();
            $pendingCount += EventVolunteer::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'pending')->count();
            $approvedCount += EventVolunteer::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'approved')->count();
            $declinedCount += EventVolunteer::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'declined')->count();
        }

        if (Schema::hasTable('volunteer_registrations')) {
            $totalApplications += VolunteerRegistration::whereBetween('created_at', [$cStart, $cEnd])->count();
            $pendingCount += VolunteerRegistration::whereBetween('created_at', [$cStart, $cEnd])->whereIn('status', ['new', 'pending'])->count();
            $approvedCount += VolunteerRegistration::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'approved')->count();
            $declinedCount += VolunteerRegistration::whereBetween('created_at', [$cStart, $cEnd])->where('status', 'declined')->count();
        }

        $approvalRate = $totalApplications > 0 ? round(($approvedCount / $totalApplications) * 100, 1) : 0.0;

        // Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $dayApps = 0;
            $dayAppr = 0;

            if (Schema::hasTable('ems_event_volunteers')) {
                $dayApps += EventVolunteer::whereBetween('created_at', [$dayStart, $dayEnd])->count();
                $dayAppr += EventVolunteer::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', 'approved')->count();
            }

            if (Schema::hasTable('volunteer_registrations')) {
                $dayApps += VolunteerRegistration::whereBetween('created_at', [$dayStart, $dayEnd])->count();
                $dayAppr += VolunteerRegistration::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', 'approved')->count();
            }

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'applications' => $dayApps,
                'approved' => $dayAppr,
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
                'total_applications' => $totalApplications,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'declined' => $declinedCount,
                'approval_rate' => $approvalRate,
            ],
            'trends' => $trends,
        ];
    }
}
