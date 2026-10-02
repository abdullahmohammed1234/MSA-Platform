<?php

namespace App\Services\Intelligence;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VolunteerIntelligenceService
{
    private EmsIntelligenceService $helper;

    public function __construct(EmsIntelligenceService $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Compute authoritative operational intelligence for the Volunteer Management System (VMS).
     */
    public function getAnalytics(
        string $period = '30d',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $opportunityId = null,
        ?int $teamId = null,
        ?int $shiftId = null
    ): array {
        $bounds = $this->helper->resolveDateBounds($period, $startDate, $endDate);
        $cStart = $bounds['current']['start'];
        $cEnd = $bounds['current']['end'];
        $pStart = $bounds['previous']['start'];
        $pEnd = $bounds['previous']['end'];

        // If VMS table is not migrated yet, return safe fallback envelope
        if (!Schema::hasTable('volunteering_signups')) {
            return $this->emptyEnvelope($period, $cStart, $cEnd);
        }

        // Base query builder helper for VMS Signups
        $buildQuery = function ($start, $end) use ($opportunityId, $teamId, $shiftId) {
            $query = Signup::whereBetween('created_at', [$start, $end]);
            if ($opportunityId) {
                $query->where('opportunity_id', $opportunityId);
            }
            if ($teamId) {
                $query->where('team_id', $teamId);
            }
            if ($shiftId) {
                $query->where('shift_id', $shiftId);
            }
            return $query;
        };

        $currentQuery = $buildQuery($cStart, $cEnd);
        $previousQuery = $buildQuery($pStart, $pEnd);

        // Signups Counts in Current Period
        $totalSignups = (clone $currentQuery)->count();
        $confirmedSignups = (clone $currentQuery)->where('status', 'confirmed')->count();
        $waitlistedSignups = (clone $currentQuery)->where('status', 'waitlisted')->count();
        $cancelledSignups = (clone $currentQuery)->where('status', 'cancelled')->count();
        $completedSignups = (clone $currentQuery)->where('status', 'completed')->count();
        $noShowSignups = (clone $currentQuery)->where(function ($q) {
            $q->where('status', 'no_show')->orWhere('attendance_status', 'absent');
        })->count();

        // Attendance Breakdown
        $presentCount = (clone $currentQuery)->where('attendance_status', 'present')->count();
        $absentCount = (clone $currentQuery)->where('attendance_status', 'absent')->count();
        $excusedCount = (clone $currentQuery)->where('attendance_status', 'excused')->count();
        $unmarkedCount = (clone $currentQuery)->where('attendance_status', 'not_marked')->count();

        // Unique Volunteers Breakdown
        $uniqueEmailsInPeriod = (clone $currentQuery)->whereNotNull('email')->pluck('email')->map(fn ($e) => strtolower(trim($e)))->unique()->values();
        $totalVolunteers = $uniqueEmailsInPeriod->count();

        $activeVolunteers = (clone $currentQuery)->whereIn('status', ['signed_up', 'confirmed'])
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->count();

        // Returning Volunteers vs First-Time Volunteers
        $firstTimeVolunteersCount = 0;
        $returningVolunteersCount = 0;

        foreach ($uniqueEmailsInPeriod as $email) {
            $hasPriorSignup = Signup::where('email', $email)
                ->where('created_at', '<', $cStart)
                ->exists();

            if ($hasPriorSignup) {
                $returningVolunteersCount++;
            } else {
                $firstTimeVolunteersCount++;
            }
        }

        // Rates
        $attendanceDenominator = $presentCount + $absentCount + $noShowSignups;
        $attendanceRate = $attendanceDenominator > 0
            ? round(($presentCount / $attendanceDenominator) * 100, 1)
            : ($totalSignups > 0 ? round(($confirmedSignups / $totalSignups) * 100, 1) : 0.0);

        $completionDenominator = $completedSignups + $confirmedSignups + $noShowSignups;
        $completionRate = $completionDenominator > 0
            ? round(($completedSignups / $completionDenominator) * 100, 1)
            : 0.0;

        $noShowRate = $totalSignups > 0 ? round(($noShowSignups / $totalSignups) * 100, 1) : 0.0;
        $cancellationRate = $totalSignups > 0 ? round(($cancelledSignups / $totalSignups) * 100, 1) : 0.0;

        // Service Hours Calculation
        $serviceHours = $this->calculateServiceHours($currentQuery);

        // Previous Period Stats for PCT Change
        $prevTotalSignups = (clone $previousQuery)->count();
        $prevCompletedSignups = (clone $previousQuery)->where('status', 'completed')->count();
        $prevServiceHours = $this->calculateServiceHours($previousQuery);

        $pctSignups = $this->helper->calculatePctChange($totalSignups, $prevTotalSignups);
        $pctCompleted = $this->helper->calculatePctChange($completedSignups, $prevCompletedSignups);
        $pctHours = $this->helper->calculatePctChange($serviceHours, $prevServiceHours);

        // Operational Overview & Attention Required Items
        $opportunityQuery = Opportunity::query();
        if ($opportunityId) {
            $opportunityQuery->where('id', $opportunityId);
        }
        $totalOpportunities = (clone $opportunityQuery)->count();

        // Understaffed Shifts
        $shiftsQuery = Shift::with(['opportunity', 'signups'])->where('status', 'open');
        if ($opportunityId) {
            $shiftsQuery->where('opportunity_id', $opportunityId);
        }
        if ($shiftId) {
            $shiftsQuery->where('id', $shiftId);
        }

        $allShifts = $shiftsQuery->get();
        $understaffedShifts = $allShifts->filter(function ($shift) {
            if ($shift->capacity === null) return false;
            $activeCount = $shift->signups->whereIn('status', ['signed_up', 'confirmed', 'completed'])->count();
            return $activeCount < $shift->capacity;
        });

        // Unmarked Attendance Shifts
        $unmarkedPastShifts = $allShifts->filter(function ($shift) {
            if (!$shift->end_at || $shift->end_at->isFuture()) return false;
            return $shift->signups->where('attendance_status', 'not_marked')->whereIn('status', ['signed_up', 'confirmed'])->count() > 0;
        });

        $waitlistedOppCount = Opportunity::whereHas('signups', function ($q) {
            $q->where('status', 'waitlisted');
        })->count();

        $attentionRequired = [];
        if ($unmarkedPastShifts->count() > 0) {
            $attentionRequired[] = [
                'id' => 'unmarked_attendance',
                'type' => 'unmarked_attendance',
                'title' => 'Pending Attendance Recording',
                'count' => $unmarkedPastShifts->count(),
                'description' => "{$unmarkedPastShifts->count()} past shift(s) have unrecorded volunteer attendance.",
                'severity' => 'warning',
            ];
        }

        if ($understaffedShifts->count() > 0) {
            $attentionRequired[] = [
                'id' => 'understaffed_shifts',
                'type' => 'understaffed_shifts',
                'title' => 'Understaffed Volunteer Shifts',
                'count' => $understaffedShifts->count(),
                'description' => "{$understaffedShifts->count()} upcoming shift(s) are below maximum volunteer capacity.",
                'severity' => 'info',
            ];
        }

        if ($waitlistedOppCount > 0) {
            $attentionRequired[] = [
                'id' => 'waitlist_pending',
                'type' => 'waitlist_pending',
                'title' => 'Active Volunteer Waitlists',
                'count' => $waitlistedOppCount,
                'description' => "{$waitlistedOppCount} opportunity(s) have volunteers waiting for open capacity.",
                'severity' => 'info',
            ];
        }

        // Daily Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $dayQuery = $buildQuery($dayStart, $dayEnd);

            $daySignups = (clone $dayQuery)->count();
            $dayConfirmed = (clone $dayQuery)->where('status', 'confirmed')->count();
            $dayCompleted = (clone $dayQuery)->where('status', 'completed')->count();
            $dayHours = $this->calculateServiceHours($dayQuery);

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'signups' => $daySignups,
                'confirmed' => $dayConfirmed,
                'completed' => $dayCompleted,
                'service_hours' => $dayHours,
                'applications' => $daySignups,
                'approved' => $dayConfirmed + $dayCompleted,
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
                'total_volunteers' => [
                    'current' => $totalVolunteers,
                    'pct_change' => 0.0,
                ],
                'active_volunteers' => [
                    'current' => $activeVolunteers,
                    'pct_change' => 0.0,
                ],
                'first_time_volunteers' => [
                    'current' => $firstTimeVolunteersCount,
                ],
                'returning_volunteers' => [
                    'current' => $returningVolunteersCount,
                ],
                'total_signups' => [
                    'current' => $totalSignups,
                    'previous' => $prevTotalSignups,
                    'pct_change' => $pctSignups,
                ],
                'confirmed_signups' => $confirmedSignups,
                'waitlisted_signups' => $waitlistedSignups,
                'cancelled_signups' => $cancelledSignups,
                'completed_signups' => [
                    'current' => $completedSignups,
                    'previous' => $prevCompletedSignups,
                    'pct_change' => $pctCompleted,
                ],
                'no_show_signups' => $noShowSignups,
                'attendance_rate' => $attendanceRate,
                'completion_rate' => $completionRate,
                'no_show_rate' => $noShowRate,
                'cancellation_rate' => $cancellationRate,
                'total_service_hours' => [
                    'current' => $serviceHours,
                    'previous' => $prevServiceHours,
                    'pct_change' => $pctHours,
                ],
                // Legacy compatibility fields
                'total_applications' => $totalSignups,
                'pending' => (clone $currentQuery)->where('status', 'signed_up')->count(),
                'approved' => $confirmedSignups + $completedSignups,
                'declined' => $cancelledSignups + $noShowSignups,
                'approval_rate' => $attendanceRate,
            ],
            'attendance_breakdown' => [
                'present' => $presentCount,
                'absent' => $absentCount,
                'excused' => $excusedCount,
                'not_marked' => $unmarkedCount,
            ],
            'funnel' => [
                'signups' => $totalSignups,
                'confirmed' => $confirmedSignups,
                'attended' => $presentCount,
                'completed' => $completedSignups,
            ],
            'operational_summary' => [
                'total_opportunities' => $totalOpportunities,
                'understaffed_shifts' => $understaffedShifts->count(),
                'unmarked_past_shifts' => $unmarkedPastShifts->count(),
                'waitlisted_opportunities' => $waitlistedOppCount,
            ],
            'attention_required' => $attentionRequired,
            'trends' => $trends,
        ];
    }

    /**
     * Compute authoritative service hours derived from shift/opportunity durations.
     */
    public function calculateServiceHours($signupQuery): float
    {
        $qualifyingSignups = (clone $signupQuery)
            ->with(['shift', 'opportunity'])
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhere(function ($sub) {
                      $sub->where('attendance_status', 'present')
                          ->where('status', 'confirmed');
                  });
            })
            ->get();

        $totalMinutes = 0;
        foreach ($qualifyingSignups as $signup) {
            $start = $signup->shift?->start_at ?? $signup->opportunity?->start_at;
            $end = $signup->shift?->end_at ?? $signup->opportunity?->end_at;

            if ($start && $end && $end->ne($start)) {
                $totalMinutes += abs($start->diffInMinutes($end));
            }
        }

        return round($totalMinutes / 60.0, 1);
    }

    private function emptyEnvelope(string $period, Carbon $cStart, Carbon $cEnd): array
    {
        return [
            'period' => [
                'type' => $period,
                'current_start' => $cStart->toIso8601String(),
                'current_end' => $cEnd->toIso8601String(),
            ],
            'kpis' => [
                'total_volunteers' => ['current' => 0, 'pct_change' => 0],
                'active_volunteers' => ['current' => 0, 'pct_change' => 0],
                'first_time_volunteers' => ['current' => 0],
                'returning_volunteers' => ['current' => 0],
                'total_signups' => ['current' => 0, 'previous' => 0, 'pct_change' => 0],
                'confirmed_signups' => 0,
                'waitlisted_signups' => 0,
                'cancelled_signups' => 0,
                'completed_signups' => ['current' => 0, 'previous' => 0, 'pct_change' => 0],
                'no_show_signups' => 0,
                'attendance_rate' => 0.0,
                'completion_rate' => 0.0,
                'no_show_rate' => 0.0,
                'cancellation_rate' => 0.0,
                'total_service_hours' => ['current' => 0.0, 'previous' => 0.0, 'pct_change' => 0],
                'total_applications' => 0,
                'pending' => 0,
                'approved' => 0,
                'declined' => 0,
                'approval_rate' => 0.0,
            ],
            'attendance_breakdown' => [
                'present' => 0,
                'absent' => 0,
                'excused' => 0,
                'not_marked' => 0,
            ],
            'funnel' => [
                'signups' => 0,
                'confirmed' => 0,
                'attended' => 0,
                'completed' => 0,
            ],
            'operational_summary' => [
                'total_opportunities' => 0,
                'understaffed_shifts' => 0,
                'unmarked_past_shifts' => 0,
                'waitlisted_opportunities' => 0,
            ],
            'attention_required' => [],
            'trends' => [],
        ];
    }
}
