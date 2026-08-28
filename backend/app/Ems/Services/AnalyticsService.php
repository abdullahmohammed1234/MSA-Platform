<?php

namespace App\Ems\Services;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Payment;
use App\Ems\Models\EventFeedback;
use App\Ems\Enums\EventStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Compute overview KPIs and chart data based on matching events.
     *
     * @param  User  $user The requesting user (for visibility scoping)
     * @param  array  $filters Array of filters (start_date, end_date, event_uuid, category_id)
     * @return array
     */
    public function getDashboardPayload(User $user, array $filters = []): array
    {
        $eventQuery = Event::query()->visibleTo($user);

        if (!empty($filters['event_uuid'])) {
            $eventQuery->where('uuid', $filters['event_uuid']);
        }
        if (!empty($filters['category_id'])) {
            $eventQuery->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['start_date'])) {
            $eventQuery->where('start_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }
        if (!empty($filters['end_date'])) {
            $eventQuery->where('start_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $events = $eventQuery->get();
        $eventIds = $events->pluck('id')->all();

        if (empty($eventIds)) {
            return $this->emptyPayload();
        }

        // 1. Basic Counts & Sums
        $registrationsQuery = Registration::whereIn('event_id', $eventIds);
        $totalRegs = (int) $registrationsQuery->whereIn('status', [
            RegistrationStatus::Confirmed->value,
            RegistrationStatus::Pending->value,
            RegistrationStatus::AwaitingPayment->value
        ])->sum('quantity');

        $confirmedRegs = (int) Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->sum('quantity');

        $cancelledRegs = (int) Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Cancelled->value)
            ->sum('quantity');

        $ticketsIssued = (int) Ticket::whereIn('event_id', $eventIds)
            ->where('status', 'issued')
            ->count();

        $checkedIn = (int) CheckIn::whereIn('event_id', $eventIds)->count();

        // 2. Attendance & No-Show Rates
        // In the context of tickets, expected attendees are either issued tickets or confirmed registrations
        $expected = $ticketsIssued > 0 ? $ticketsIssued : ($confirmedRegs > 0 ? $confirmedRegs : 0);
        $noShows = max(0, $expected - $checkedIn);
        $attendanceRate = $expected > 0 ? round(($checkedIn / $expected) * 100, 1) : 0.0;
        $noShowRate = $expected > 0 ? round(($noShows / $expected) * 100, 1) : 0.0;

        // 3. Revenue Metrics
        $paymentsQuery = Payment::whereIn('registration_id', function ($query) use ($eventIds) {
            $query->select('id')->from('ems_registrations')->whereIn('event_id', $eventIds);
        });

        $grossRevenue = (float) $paymentsQuery->clone()->where('status', PaymentStatus::Paid->value)->sum('amount');
        $refundAmount = (float) $paymentsQuery->clone()->sum('amount_refunded');
        $netRevenue = max(0.0, $grossRevenue - $refundAmount);

        // Waitlist size
        $waitlistSize = (int) DB::table('ems_waitlist_entries')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'waiting')
            ->sum('quantity');

        $waitlistConversions = (int) DB::table('ems_waitlist_entries')
            ->whereIn('event_id', $eventIds)
            ->whereNotNull('promoted_at')
            ->count();

        // Capacity
        $totalCapacity = 0;
        $hasUnlimitedCapacity = false;
        foreach ($events as $event) {
            if ($event->capacity === null) {
                $hasUnlimitedCapacity = true;
            } else {
                $totalCapacity += $event->capacity;
            }
        }
        $capacityUtilization = 0.0;
        if ($totalCapacity > 0) {
            $capacityUtilization = round(($totalRegs / $totalCapacity) * 100, 1);
        }

        // 4. Registration Trends Over Time
        $registrationTrends = Registration::whereIn('event_id', $eventIds)
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->all();

        // 5. Member / Attendee Breakdown
        $breakdown = $this->computeBreakdown($eventIds);

        // 6. Ticket Type Performance
        $ticketPerformance = $this->computeTicketPerformance($eventIds);

        // 7. Early-Bird Analytics
        $earlyBird = $this->computeEarlyBird($eventIds);

        // 8. No-Show Breakdown (Paid vs Free)
        $noShowBreakdown = $this->computeNoShowBreakdown($eventIds, $expected, $checkedIn);

        return [
            'kpis' => [
                'total_registrations' => $totalRegs,
                'confirmed_registrations' => $confirmedRegs,
                'cancelled_registrations' => $cancelledRegs,
                'tickets_issued' => $ticketsIssued,
                'tickets_sold' => $confirmedRegs, // confirmed registers counts as tickets sold
                'checked_in' => $checkedIn,
                'no_shows' => $noShows,
                'attendance_rate' => $attendanceRate,
                'no_show_rate' => $noShowRate,
                'gross_revenue' => $grossRevenue,
                'refunds' => $refundAmount,
                'net_revenue' => $netRevenue,
                'waitlist_size' => $waitlistSize,
                'waitlist_conversions' => $waitlistConversions,
                'total_capacity' => $hasUnlimitedCapacity ? null : $totalCapacity,
                'capacity_utilization' => $capacityUtilization,
            ],
            'charts' => [
                'registrations_over_time' => $registrationTrends,
                'member_breakdown' => $breakdown,
                'ticket_performance' => $ticketPerformance,
                'early_bird' => $earlyBird,
                'no_shows' => $noShowBreakdown,
            ]
        ];
    }

    /**
     * Retrieve advanced report payload with funnel, organizer, and category performance.
     */
    public function getAdvancedReportPayload(User $user, array $filters = []): array
    {
        $eventQuery = Event::query()->visibleTo($user);

        if (!empty($filters['event_uuid'])) {
            $eventQuery->where('uuid', $filters['event_uuid']);
        }
        if (!empty($filters['category_id'])) {
            $eventQuery->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['start_date'])) {
            $eventQuery->where('start_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }
        if (!empty($filters['end_date'])) {
            $eventQuery->where('start_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }
        if (!empty($filters['organizer_id'])) {
            $eventQuery->where('organizer_id', $filters['organizer_id']);
        }
        if (!empty($filters['series_id'])) {
            $eventQuery->where('series_id', $filters['series_id']);
        }

        $events = $eventQuery->get();
        $eventIds = $events->pluck('id')->all();

        if (empty($eventIds)) {
            return [
                'funnel' => ['views' => 0, 'started' => 0, 'completed' => 0, 'tickets_issued' => 0, 'checked_in' => 0, 'rates' => []],
                'organizers' => [],
                'categories' => [],
                'trends' => [],
            ];
        }

        // 1. Conversion Funnel
        $views = (int) Event::whereIn('id', $eventIds)->sum('views_count');
        $started = (int) Event::whereIn('id', $eventIds)->sum('registrations_started_count');
        
        $completed = (int) Registration::whereIn('event_id', $eventIds)
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Pending->value, RegistrationStatus::AwaitingPayment->value])
            ->sum('quantity');

        $ticketsIssued = (int) Ticket::whereIn('event_id', $eventIds)->where('status', 'issued')->count();
        $checkedIn = (int) CheckIn::whereIn('event_id', $eventIds)->count();

        $funnel = [
            'views' => $views,
            'started' => $started,
            'completed' => $completed,
            'tickets_issued' => $ticketsIssued,
            'checked_in' => $checkedIn,
            'rates' => [
                'views_to_started' => $views > 0 ? round(($started / $views) * 100, 1) : 0.0,
                'started_to_completed' => $started > 0 ? round(($completed / $started) * 100, 1) : 0.0,
                'completed_to_tickets' => $completed > 0 ? round(($ticketsIssued / $completed) * 100, 1) : 0.0,
                'tickets_to_checked_in' => $ticketsIssued > 0 ? round(($checkedIn / $ticketsIssued) * 100, 1) : 0.0,
            ]
        ];

        // 2. Organizer Performance
        $organizerStats = [];
        $organizerGroups = Event::whereIn('id', $eventIds)
            ->select('organizer_id', DB::raw('count(*) as count'))
            ->groupBy('organizer_id')
            ->get();

        foreach ($organizerGroups as $group) {
            $orgId = $group->organizer_id;
            if (!$orgId) continue;

            $orgEvents = Event::whereIn('id', $eventIds)->where('organizer_id', $orgId)->get();
            $orgEventIds = $orgEvents->pluck('id')->all();

            $orgUser = User::find($orgId);
            $orgName = $orgUser ? $orgUser->name : 'Unknown Organizer';

            $orgCheckedIn = CheckIn::whereIn('event_id', $orgEventIds)->count();
            $orgTickets = Ticket::whereIn('event_id', $orgEventIds)->where('status', 'issued')->count();
            $orgConf = Registration::whereIn('event_id', $orgEventIds)->where('status', RegistrationStatus::Confirmed->value)->sum('quantity');
            $orgExpected = $orgTickets > 0 ? $orgTickets : ($orgConf > 0 ? $orgConf : 0);

            $orgAttendanceRate = $orgExpected > 0 ? round(($orgCheckedIn / $orgExpected) * 100, 1) : 0.0;
            $orgAvgRating = round(EventFeedback::whereIn('event_id', $orgEventIds)->avg('overall_rating') ?: 0.0, 2);

            $orgRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
                ->whereIn('registration_id', function ($query) use ($orgEventIds) {
                    $query->select('id')->from('ems_registrations')->whereIn('event_id', $orgEventIds);
                })
                ->sum('amount');

            $organizerStats[] = [
                'organizer_id' => $orgId,
                'name' => $orgName,
                'events_organized' => $orgEvents->count(),
                'average_attendance' => round($orgCheckedIn / max(1, $orgEvents->count()), 1),
                'attendance_rate' => $orgAttendanceRate,
                'average_feedback_rating' => $orgAvgRating,
                'revenue_generated' => $orgRevenue,
            ];
        }

        // 3. Event Type / Category Performance
        $categoryStats = [];
        $categoryGroups = Event::whereIn('id', $eventIds)
            ->select('category_id', DB::raw('count(*) as count'))
            ->groupBy('category_id')
            ->get();

        foreach ($categoryGroups as $group) {
            $catId = $group->category_id;
            if (!$catId) continue;

            $catEvents = Event::whereIn('id', $eventIds)->where('category_id', $catId)->get();
            $catEventIds = $catEvents->pluck('id')->all();

            $categoryModel = \App\Ems\Models\EventCategory::find($catId);
            $catName = $categoryModel ? $categoryModel->name : 'Other';

            $catCheckedIn = CheckIn::whereIn('event_id', $catEventIds)->count();
            $catTickets = Ticket::whereIn('event_id', $catEventIds)->where('status', 'issued')->count();
            $catConf = Registration::whereIn('event_id', $catEventIds)->where('status', RegistrationStatus::Confirmed->value)->sum('quantity');
            $catExpected = $catTickets > 0 ? $catTickets : ($catConf > 0 ? $catConf : 0);

            $catNoShows = max(0, $catExpected - $catCheckedIn);
            $catNoShowRate = $catExpected > 0 ? round(($catNoShows / $catExpected) * 100, 1) : 0.0;
            $catAvgRating = round(EventFeedback::whereIn('event_id', $catEventIds)->avg('overall_rating') ?: 0.0, 2);

            $catRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
                ->whereIn('registration_id', function ($query) use ($catEventIds) {
                    $query->select('id')->from('ems_registrations')->whereIn('event_id', $catEventIds);
                })
                ->sum('amount');

            $categoryStats[] = [
                'category_id' => $catId,
                'name' => $catName,
                'events_count' => $catEvents->count(),
                'average_attendance' => round($catCheckedIn / max(1, $catEvents->count()), 1),
                'average_feedback_rating' => $catAvgRating,
                'no_show_rate' => $catNoShowRate,
                'revenue_generated' => $catRevenue,
            ];
        }

        // 4. Monthly Timeline Trends
        $trends = [];
        $trendQuery = Event::whereIn('id', $eventIds)->orderBy('start_at', 'asc')->get();
        $groupedByMonth = $trendQuery->groupBy(fn($e) => $e->start_at ? $e->start_at->format('Y-m') : 'Unknown');

        foreach ($groupedByMonth as $month => $monthEvents) {
            if ($month === 'Unknown') continue;

            $mEventIds = $monthEvents->pluck('id')->all();

            $mRegs = (int) Registration::whereIn('event_id', $mEventIds)->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value
            ])->sum('quantity');

            $mCheckedIn = CheckIn::whereIn('event_id', $mEventIds)->count();

            $mRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
                ->whereIn('registration_id', function ($query) use ($mEventIds) {
                    $query->select('id')->from('ems_registrations')->whereIn('event_id', $mEventIds);
                })
                ->sum('amount');

            $trends[] = [
                'period' => $month,
                'registrations' => $mRegs,
                'attendance' => $mCheckedIn,
                'revenue' => $mRevenue,
            ];
        }

        return [
            'funnel' => $funnel,
            'organizers' => $organizerStats,
            'categories' => $categoryStats,
            'trends' => $trends,
        ];
    }

    /**
     * Compute member type breakdown.
     */
    private function computeBreakdown(array $eventIds): array
    {
        $guestsQty = (int) Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereNull('user_id')
            ->sum('quantity');

        $userQuantities = Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('user_id')
            ->get()
            ->pluck('qty', 'user_id');

        $userIds = $userQuantities->keys()->all();
        
        $userRoles = [];
        if (!empty($userIds)) {
            $userRoles = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->whereIn('role_user.user_id', $userIds)
                ->select('role_user.user_id', 'roles.slug')
                ->get()
                ->groupBy('user_id');
        }

        $counts = [
            'members' => 0,
            'volunteers' => 0,
            'students' => 0,
            'guests' => $guestsQty,
            'others' => 0,
        ];

        foreach ($userQuantities as $userId => $qty) {
            $qty = (int) $qty;
            $roles = isset($userRoles[$userId]) ? $userRoles[$userId]->pluck('slug')->all() : [];

            if (in_array('member', $roles, true)) {
                $counts['members'] += $qty;
            } elseif (in_array('volunteer', $roles, true)) {
                $counts['volunteers'] += $qty;
            } elseif (in_array('student', $roles, true)) {
                $counts['students'] += $qty;
            } else {
                $counts['others'] += $qty;
            }
        }

        $total = array_sum($counts);
        $percentages = [];
        foreach ($counts as $key => $val) {
            $percentages[$key] = $total > 0 ? round(($val / $total) * 100, 1) : 0.0;
        }

        return [
            'counts' => $counts,
            'percentages' => $percentages,
            'total' => $total,
        ];
    }

    /**
     * Compute ticket type performance.
     */
    private function computeTicketPerformance(array $eventIds): array
    {
        $ticketTypes = TicketType::whereIn('event_id', $eventIds)->get();
        
        $revenues = [];
        if ($ticketTypes->isNotEmpty()) {
            $revenues = Payment::where('ems_payments.status', PaymentStatus::Paid->value)
                ->whereIn('ems_payments.registration_id', function ($query) use ($eventIds) {
                    $query->select('id')->from('ems_registrations')->whereIn('event_id', $eventIds);
                })
                ->join('ems_registrations', 'ems_payments.registration_id', '=', 'ems_registrations.id')
                ->select('ems_registrations.ticket_type_id', DB::raw('SUM(ems_payments.amount) as revenue'))
                ->groupBy('ems_registrations.ticket_type_id')
                ->pluck('revenue', 'ems_registrations.ticket_type_id')
                ->all();
        }

        $performance = [];

        foreach ($ticketTypes as $type) {
            $sold = (int) $type->quantity_sold;
            $capacity = $type->quantity; // Null means unlimited
            $remaining = $capacity !== null ? max(0, $capacity - $sold) : null;
            $sellThrough = ($capacity !== null && $capacity > 0) ? round(($sold / $capacity) * 100, 1) : null;

            $revenue = (float) ($revenues[$type->id] ?? 0.0);

            $performance[] = [
                'id' => $type->id,
                'name' => $type->name,
                'price' => (float) $type->price,
                'capacity' => $capacity,
                'sold' => $sold,
                'remaining' => $remaining,
                'sell_through' => $sellThrough,
                'revenue' => $revenue,
            ];
        }

        return $performance;
    }

    /**
     * Compute early-bird metrics.
     */
    private function computeEarlyBird(array $eventIds): array
    {
        $ticketTypes = TicketType::whereIn('event_id', $eventIds)->get();

        $earlyBirdTypes = $ticketTypes->filter(fn($t) => stripos($t->name, 'early') !== false);
        $standardTypes = $ticketTypes->filter(fn($t) => stripos($t->name, 'standard') !== false || stripos($t->name, 'regular') !== false);
        $vipTypes = $ticketTypes->filter(fn($t) => stripos($t->name, 'vip') !== false);

        $earlySold = $earlyBirdTypes->sum('quantity_sold');
        $standardSold = $standardTypes->sum('quantity_sold');
        $vipSold = $vipTypes->sum('quantity_sold');

        $earlyRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
            ->whereIn('registration_id', function ($query) use ($earlyBirdTypes) {
                $query->select('id')->from('ems_registrations')->whereIn('ticket_type_id', $earlyBirdTypes->pluck('id'));
            })
            ->sum('amount');

        $standardRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
            ->whereIn('registration_id', function ($query) use ($standardTypes) {
                $query->select('id')->from('ems_registrations')->whereIn('ticket_type_id', $standardTypes->pluck('id'));
            })
            ->sum('amount');

        $vipRevenue = (float) Payment::where('status', PaymentStatus::Paid->value)
            ->whereIn('registration_id', function ($query) use ($vipTypes) {
                $query->select('id')->from('ems_registrations')->whereIn('ticket_type_id', $vipTypes->pluck('id'));
            })
            ->sum('amount');

        $earlyInventory = 0;
        foreach ($earlyBirdTypes as $t) {
            if ($t->quantity !== null) {
                $earlyInventory += max(0, $t->quantity - $t->quantity_sold);
            }
        }

        return [
            'comparison' => [
                'early_bird' => ['sold' => $earlySold, 'revenue' => $earlyRevenue],
                'standard' => ['sold' => $standardSold, 'revenue' => $standardRevenue],
                'vip' => ['sold' => $vipSold, 'revenue' => $vipRevenue],
            ],
            'remaining_inventory' => $earlyInventory,
        ];
    }

    /**
     * Compute no-show breakdowns between paid & free tickets.
     */
    private function computeNoShowBreakdown(array $eventIds, int $expected, int $checkedIn): array
    {
        // Paid registrations where ticket is issued but not checked in
        $paidNoShows = Ticket::whereIn('event_id', $eventIds)
            ->where('status', 'issued')
            ->whereIn('registration_id', function($q) {
                $q->select('id')->from('ems_registrations')->where('type', 'paid');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('ems_check_ins')->whereColumn('ems_check_ins.ticket_id', 'ems_tickets.id');
            })
            ->count();

        // Free registrations where ticket is issued but not checked in
        $freeNoShows = Ticket::whereIn('event_id', $eventIds)
            ->where('status', 'issued')
            ->whereIn('registration_id', function($q) {
                $q->select('id')->from('ems_registrations')->where('type', 'free');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('ems_check_ins')->whereColumn('ems_check_ins.ticket_id', 'ems_tickets.id');
            })
            ->count();

        $totalNoShows = $paidNoShows + $freeNoShows;

        return [
            'total' => $totalNoShows,
            'paid' => $paidNoShows,
            'free' => $freeNoShows,
            'rate' => $expected > 0 ? round(($totalNoShows / $expected) * 100, 1) : 0.0,
        ];
    }

    /**
     * Side-by-side event comparison.
     */
    public function getEventComparison(User $user, array $eventUuids): array
    {
        $events = Event::query()
            ->visibleTo($user)
            ->whereIn('uuid', $eventUuids)
            ->get();

        $comparison = [];

        foreach ($events as $event) {
            $payload = $this->getDashboardPayload($user, ['event_uuid' => $event->uuid]);
            $kpis = $payload['kpis'];

            $comparison[] = [
                'uuid' => $event->uuid,
                'name' => $event->name,
                'start_at' => $event->start_at ? $event->start_at->toIso8601String() : null,
                'capacity' => $event->capacity,
                'registrations' => $kpis['total_registrations'],
                'checked_in' => $kpis['checked_in'],
                'no_shows' => $kpis['no_shows'],
                'attendance_rate' => $kpis['attendance_rate'],
                'no_show_rate' => $kpis['no_show_rate'],
                'gross_revenue' => $kpis['gross_revenue'],
                'refunds' => $kpis['refunds'],
                'net_revenue' => $kpis['net_revenue'],
                'waitlist_size' => $kpis['waitlist_size'],
            ];
        }

        return $comparison;
    }

    /**
     * An empty payload placeholder.
     */
    private function emptyPayload(): array
    {
        return [
            'kpis' => [
                'total_registrations' => 0,
                'confirmed_registrations' => 0,
                'cancelled_registrations' => 0,
                'tickets_issued' => 0,
                'tickets_sold' => 0,
                'checked_in' => 0,
                'no_shows' => 0,
                'attendance_rate' => 0.0,
                'no_show_rate' => 0.0,
                'gross_revenue' => 0.0,
                'refunds' => 0.0,
                'net_revenue' => 0.0,
                'waitlist_size' => 0,
                'waitlist_conversions' => 0,
                'total_capacity' => 0,
                'capacity_utilization' => 0.0,
            ],
            'charts' => [
                'registrations_over_time' => [],
                'member_breakdown' => [
                    'counts' => ['members' => 0, 'volunteers' => 0, 'students' => 0, 'guests' => 0, 'others' => 0],
                    'percentages' => ['members' => 0.0, 'volunteers' => 0.0, 'students' => 0.0, 'guests' => 0.0, 'others' => 0.0],
                    'total' => 0
                ],
                'ticket_performance' => [],
                'early_bird' => [
                    'comparison' => [
                        'early_bird' => ['sold' => 0, 'revenue' => 0.0],
                        'standard' => ['sold' => 0, 'revenue' => 0.0],
                        'vip' => ['sold' => 0, 'revenue' => 0.0],
                    ],
                    'remaining_inventory' => 0
                ],
                'no_shows' => [
                    'total' => 0,
                    'paid' => 0,
                    'free' => 0,
                    'rate' => 0.0
                ]
            ]
        ];
    }
}
