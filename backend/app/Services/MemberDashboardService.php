<?php

namespace App\Services;

use App\Ems\Models\Registration as EmsRegistration;
use App\Models\CMS\Announcement;
use App\Models\CertificateAward;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Store\Models\StoreOrder;
use App\Volunteering\Models\Opportunity as VolunteeringOpportunity;
use App\Volunteering\Models\Signup as VolunteeringSignup;
use App\Volunteering\Models\VolunteerProfile;

class MemberDashboardService
{
    protected ApplicationAccessService $appAccessService;
    protected AccountSummaryService $accountSummaryService;

    public function __construct(
        ApplicationAccessService $appAccessService,
        AccountSummaryService $accountSummaryService
    ) {
        $this->appAccessService = $appAccessService;
        $this->accountSummaryService = $accountSummaryService;
    }

    /**
     * Build full member dashboard payload for authenticated user.
     * All queries are strictly scoped by $user->id (no email matching fallbacks).
     */
    public function getDashboardData(User $user): array
    {
        $userId = $user->id;
        $user->loadMissing(['roles']);

        // 1. Core User Identity & Status
        $communityStatus = 'Active Member';
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            $communityStatus = 'Administrator';
        } elseif ($user->hasRole('mentor')) {
            $communityStatus = 'Mentor';
        } elseif ($user->hasRole('volunteer')) {
            $communityStatus = 'Volunteer';
        }

        $userPayload = [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'community_status' => $communityStatus,
            'email_verified' => !is_null($user->email_verified_at),
        ];

        // 2. Next Upcoming Event Spotlight & Total Count
        $nextRegistration = EmsRegistration::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'awaiting_payment', 'waitlisted', 'pending'])
            ->whereHas('event', function ($query) {
                $query->where('start_at', '>=', now()->subHours(6));
            })
            ->with(['event.category', 'tickets', 'ticketType'])
            ->get()
            ->sortBy(function ($reg) {
                return $reg->event?->start_at?->timestamp ?? PHP_INT_MAX;
            })
            ->first();

        $upcomingEventsCount = EmsRegistration::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'awaiting_payment', 'waitlisted', 'pending'])
            ->whereHas('event', function ($query) {
                $query->where('start_at', '>=', now()->subHours(6));
            })
            ->count();

        $nextEventPayload = null;
        if ($nextRegistration && $nextRegistration->event) {
            $firstTicket = $nextRegistration->tickets->first();
            $nextEventPayload = [
                'registration_id' => $nextRegistration->id,
                'registration_uuid' => $nextRegistration->uuid,
                'reference' => $nextRegistration->reference,
                'status' => $nextRegistration->status->value ?? (string) $nextRegistration->status,
                'event_title' => $nextRegistration->event->title ?: $nextRegistration->event->name,
                'event_slug' => $nextRegistration->event->slug,
                'start_at' => $nextRegistration->event->start_at?->toIso8601String(),
                'end_at' => $nextRegistration->event->end_at?->toIso8601String(),
                'location' => $nextRegistration->event->location_name ?? $nextRegistration->event->location,
                'category' => $nextRegistration->event->category?->name,
                'ticket_code' => $firstTicket?->code ?? null,
                'amount_due' => (float) $nextRegistration->amount_due,
            ];
        }

        // 3. Volunteering Snapshot & Personal Commitments (Scope C)
        $volunteerProfile = VolunteerProfile::query()
            ->where('user_id', $userId)
            ->with(['skills', 'interests'])
            ->first();

        $userSignups = VolunteeringSignup::query()
            ->where('user_id', $userId)
            ->with(['opportunity'])
            ->get();

        $upcomingShiftsCount = $userSignups->filter(function ($s) {
            return in_array($s->status, ['pending', 'approved', 'confirmed'], true);
        })->count();

        $attendedCount = $userSignups->filter(function ($s) {
            return $s->attendance_status === 'attended';
        })->count();

        $absentCount = $userSignups->filter(function ($s) {
            return $s->attendance_status === 'absent';
        })->count();

        // Confirmed upcoming volunteer commitments
        $commitmentsPayload = $userSignups->filter(function ($s) {
            return in_array($s->status, ['approved', 'confirmed'], true) &&
                $s->opportunity &&
                (!$s->opportunity->start_at || $s->opportunity->start_at >= now()->subHours(6));
        })->take(5)->map(function ($s) {
            return [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'opportunity_title' => $s->opportunity->title,
                'opportunity_slug' => $s->opportunity->slug,
                'status' => $s->status,
                'start_at' => $s->opportunity->start_at?->toIso8601String(),
                'location' => $s->opportunity->location,
                'path' => "/volunteer/{$s->opportunity->slug}",
            ];
        })->values()->toArray();

        // Pending applications
        $pendingAppsPayload = $userSignups->filter(function ($s) {
            return $s->status === 'pending' && $s->opportunity;
        })->take(5)->map(function ($s) {
            return [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'opportunity_title' => $s->opportunity->title,
                'opportunity_slug' => $s->opportunity->slug,
                'status' => 'pending',
                'created_at' => $s->created_at?->toIso8601String(),
                'path' => "/volunteer/{$s->opportunity->slug}",
            ];
        })->values()->toArray();

        $volunteerSnapshot = null;
        if ($volunteerProfile || $userSignups->isNotEmpty() || $user->hasRole('volunteer')) {
            $volunteerSnapshot = [
                'status' => $volunteerProfile ? 'active' : ($user->hasRole('volunteer') ? 'registered' : 'member'),
                'completion_percentage' => $volunteerProfile?->profile_completion_percentage ?? 0,
                'upcoming_shifts' => $upcomingShiftsCount,
                'completed_shifts' => $attendedCount,
                'skills' => $volunteerProfile ? $volunteerProfile->skills->pluck('name')->toArray() : [],
                'commitments' => $commitmentsPayload,
                'pending_applications' => $pendingAppsPayload,
                'attendance_summary' => [
                    'attended_count' => $attendedCount,
                    'absent_count' => $absentCount,
                    'attendance_note' => 'Attendance and service record are recorded strictly based on verified shift check-ins.',
                ],
            ];
        }

        // 4. Learning Snapshot (Academy)
        $activeEnrollment = Enrollment::query()
            ->where('user_id', $userId)
            ->whereNull('completed_at')
            ->with(['course'])
            ->orderBy('enrolled_at', 'desc')
            ->first();

        $completedCoursesCount = Enrollment::query()
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->count();

        $certificatesCount = CertificateAward::query()
            ->where('user_id', $userId)
            ->count();

        $learningSnapshot = [
            'active_course' => $activeEnrollment && $activeEnrollment->course ? [
                'id' => $activeEnrollment->course->id,
                'title' => $activeEnrollment->course->title,
                'slug' => $activeEnrollment->course->slug ?? null,
                'status' => $activeEnrollment->status,
                'enrolled_at' => $activeEnrollment->enrolled_at?->toIso8601String(),
            ] : null,
            'completed_courses' => $completedCoursesCount,
            'certificates_count' => $certificatesCount,
        ];

        // 5. Recent Store Order Snapshot
        $recentOrderModel = StoreOrder::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        $recentOrderPayload = null;
        if ($recentOrderModel) {
            $recentOrderPayload = [
                'id' => $recentOrderModel->id,
                'order_number' => $recentOrderModel->order_number,
                'status' => $recentOrderModel->status,
                'payment_status' => (string) ($recentOrderModel->payment_status->value ?? $recentOrderModel->payment_status),
                'fulfillment_status' => (string) ($recentOrderModel->fulfillment_status->value ?? $recentOrderModel->fulfillment_status),
                'formatted_total' => $recentOrderModel->formatted_total,
                'created_at' => $recentOrderModel->created_at?->toIso8601String(),
            ];
        }

        // 6. Unread Notifications Preview (Phase 33)
        $unreadQuery = $user->customNotifications()->unread();
        $unreadCount = (clone $unreadQuery)->count();
        $latestNotifications = (clone $unreadQuery)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'uuid' => $notif->uuid,
                    'type' => $notif->type,
                    'title' => $notif->title,
                    'message' => $notif->message,
                    'read_at' => $notif->read_at?->toIso8601String(),
                    'created_at' => $notif->created_at?->toIso8601String(),
                ];
            });

        // 7. Application Access Grid
        $rawAccess = $this->appAccessService->accessibleApplications($user);
        $applications = $this->formatApplications($rawAccess);

        // 8. Dynamic Action Items (Scope A)
        $actionItems = $this->deriveActionItems(
            $user,
            $volunteerProfile,
            $nextRegistration,
            $unreadCount,
            $userSignups
        );

        // 9. Explainable Volunteer Opportunity Recommendations (Scope B)
        $recommendedOpportunities = $this->getRecommendedOpportunities($user, $volunteerProfile, $userSignups);

        return [
            'user' => $userPayload,
            'action_items' => $actionItems,
            'recommended_opportunities' => $recommendedOpportunities,
            'next_event' => $nextEventPayload,
            'upcoming_events_count' => $upcomingEventsCount,
            'volunteer' => $volunteerSnapshot,
            'learning' => $learningSnapshot,
            'recent_order' => $recentOrderPayload,
            'notifications' => [
                'unread_count' => $unreadCount,
                'latest' => $latestNotifications,
            ],
            'applications' => $applications,
        ];
    }

    /**
     * Derive action items deterministically based on authoritative user state (Scope A).
     */
    protected function deriveActionItems(
        User $user,
        ?VolunteerProfile $volunteerProfile,
        ?EmsRegistration $nextRegistration,
        int $unreadCount,
        $userSignups = null
    ): array {
        $items = [];

        // 1. Email Verification Item
        if (is_null($user->email_verified_at)) {
            $items[] = [
                'id' => 'verify_email',
                'type' => 'warning',
                'priority' => 1,
                'title' => 'Verify Your Email Address',
                'description' => 'Verify your email address to unlock full platform features and receive instant event notifications.',
                'action_label' => 'Verify Email',
                'action_path' => '/account',
            ];
        }

        // 2. Pending Event Payment Item
        if ($nextRegistration && strtolower((string) ($nextRegistration->status->value ?? $nextRegistration->status)) === 'awaiting_payment') {
            $eventTitle = $nextRegistration->event?->title ?: $nextRegistration->event?->name;
            $items[] = [
                'id' => 'complete_event_payment',
                'type' => 'urgent',
                'priority' => 2,
                'title' => 'Complete Event Ticket Payment',
                'description' => "Your registration for {$eventTitle} is awaiting payment. Complete checkout to secure your ticket.",
                'action_label' => 'Complete Payment',
                'action_path' => "/events/{$nextRegistration->event?->slug}",
            ];
        }

        // 3. Pending Volunteer Signup Application
        if ($userSignups) {
            $pendingSignup = $userSignups->first(function ($s) {
                return $s->status === 'pending' && $s->opportunity;
            });
            if ($pendingSignup) {
                $oppTitle = $pendingSignup->opportunity->title;
                $items[] = [
                    'id' => "pending_volunteer_signup_{$pendingSignup->id}",
                    'type' => 'info',
                    'priority' => 3,
                    'title' => "Volunteer Application Under Review: {$oppTitle}",
                    'description' => "Your volunteer application for {$oppTitle} is being processed by MSA team leads.",
                    'action_label' => 'View Position',
                    'action_path' => "/volunteer/{$pendingSignup->opportunity->slug}",
                ];
            }
        }

        // 4. Upcoming Confirmed Volunteer Shift
        if ($userSignups) {
            $upcomingShift = $userSignups->first(function ($s) {
                return in_array($s->status, ['approved', 'confirmed'], true) &&
                    $s->opportunity &&
                    (!$s->opportunity->start_at || $s->opportunity->start_at >= now()->subHours(6));
            });
            if ($upcomingShift) {
                $oppTitle = $upcomingShift->opportunity->title;
                $startDate = $upcomingShift->opportunity->start_at ? $upcomingShift->opportunity->start_at->format('M j, Y \a\t g:i A') : 'Upcoming';
                $items[] = [
                    'id' => "upcoming_volunteer_shift_{$upcomingShift->id}",
                    'type' => 'urgent',
                    'priority' => 2,
                    'title' => "Upcoming Volunteer Commitment: {$oppTitle}",
                    'description' => "You are confirmed for {$oppTitle} ({$startDate}). Please review team guidelines.",
                    'action_label' => 'View Shift Details',
                    'action_path' => "/volunteer/{$upcomingShift->opportunity->slug}",
                ];
            }
        }

        // 5. Incomplete Volunteer Profile Item
        if ($volunteerProfile && $volunteerProfile->profile_completion_percentage < 100) {
            $items[] = [
                'id' => 'complete_volunteer_profile',
                'type' => 'info',
                'priority' => 4,
                'title' => 'Update Volunteer Profile & Skills',
                'description' => "Your volunteer profile is {$volunteerProfile->profile_completion_percentage}% complete. Add your skills to get matched with team roles.",
                'action_label' => 'Update Profile',
                'action_path' => '/volunteer/profile',
            ];
        }

        // 6. Unread Notifications Item (Phase 33)
        if ($unreadCount > 0) {
            $items[] = [
                'id' => 'unread_notifications',
                'type' => 'info',
                'priority' => 5,
                'title' => "You Have {$unreadCount} Unread Notification" . ($unreadCount > 1 ? 's' : ''),
                'description' => 'Check your latest updates regarding event tickets, volunteer shifts, and announcements.',
                'action_label' => 'View Notifications',
                'action_path' => '/notifications',
            ];
        }

        // 7. Latest Published Announcement
        $latestNotice = Announcement::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where('published_at', '>=', now()->subDays(7))
            ->orderBy('published_at', 'desc')
            ->first();

        if ($latestNotice) {
            $items[] = [
                'id' => "latest_announcement_{$latestNotice->id}",
                'type' => 'suggestion',
                'priority' => 6,
                'title' => "Notice: {$latestNotice->title}",
                'description' => $latestNotice->summary ?: \Illuminate\Support\Str::limit(strip_tags($latestNotice->content), 120),
                'action_label' => 'Read Announcement',
                'action_path' => "/announcements/{$latestNotice->slug}",
            ];
        }

        // Sort by priority rank
        usort($items, function ($a, $b) {
            return ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99);
        });

        return array_slice($items, 0, 6);
    }

    /**
     * Build explainable volunteer opportunity recommendations (Scope B).
     */
    protected function getRecommendedOpportunities(User $user, ?VolunteerProfile $volunteerProfile, $userSignups = null): array
    {
        $appliedOpportunityIds = [];
        if ($userSignups) {
            $appliedOpportunityIds = $userSignups->pluck('opportunity_id')->filter()->toArray();
        } else {
            $appliedOpportunityIds = VolunteeringSignup::query()
                ->where('user_id', $user->id)
                ->pluck('opportunity_id')
                ->filter()
                ->toArray();
        }

        $userSkillIds = [];
        if ($volunteerProfile && $volunteerProfile->relationLoaded('skills')) {
            $userSkillIds = $volunteerProfile->skills->pluck('id')->toArray();
        } elseif ($volunteerProfile) {
            $userSkillIds = $volunteerProfile->skills()->pluck('volunteering_skills.id')->toArray();
        }

        $query = VolunteeringOpportunity::query()
            ->whereIn('status', ['published', 'open', 'active'])
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '>=', now()->subHours(6));
            });

        if (!empty($appliedOpportunityIds)) {
            $query->whereNotIn('id', $appliedOpportunityIds);
        }

        $opportunities = $query->with(['skills'])->orderBy('start_at', 'asc')->limit(4)->get();

        return $opportunities->map(function ($opp) use ($userSkillIds, $volunteerProfile) {
            $reason = 'Open for volunteer signups';

            if (!empty($userSkillIds) && $opp->relationLoaded('skills') && $opp->skills->isNotEmpty()) {
                $oppSkillIds = $opp->skills->pluck('id')->toArray();
                if (!empty(array_intersect($userSkillIds, $oppSkillIds))) {
                    $reason = 'Matches your saved skills';
                }
            }

            if ($reason === 'Open for volunteer signups' && $volunteerProfile && !empty($volunteerProfile->preferred_categories)) {
                if (in_array($opp->category, (array) $volunteerProfile->preferred_categories, true)) {
                    $reason = 'Matches your saved preferences';
                }
            }

            if ($reason === 'Open for volunteer signups' && $opp->start_at && $opp->start_at->diffInDays(now()) <= 14) {
                $reason = 'Upcoming opportunity';
            }

            return [
                'id' => $opp->id,
                'uuid' => $opp->uuid,
                'title' => $opp->title,
                'slug' => $opp->slug,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags($opp->description ?? ''), 110),
                'start_at' => $opp->start_at?->toIso8601String(),
                'location' => $opp->location,
                'reason' => $reason,
                'path' => "/volunteer/{$opp->slug}",
            ];
        })->values()->toArray();
    }

    /**
     * Map raw application keys to user-facing application launch items.
     */
    protected function formatApplications(array $rawAccess): array
    {
        $definitions = [
            'main-website' => [
                'title' => 'Main Website',
                'description' => 'Explore SFU MSA programs, news, and resources.',
                'path' => '/',
                'is_admin' => false,
            ],
            'volunteering' => [
                'title' => 'Volunteer Portal',
                'description' => 'Manage volunteer shifts, team assignments, and profile.',
                'path' => '/volunteer/profile',
                'is_admin' => false,
            ],
            'vms' => [
                'title' => 'Volunteer Management System (VMS)',
                'description' => 'Administer volunteer registrations, rosters, and shift schedules.',
                'path' => '/vms/opportunities',
                'is_admin' => true,
            ],
            'ems' => [
                'title' => 'Event Management System (EMS)',
                'description' => 'MSA events ticketing, registration, and attendance portal.',
                'path' => '/admin/systems/ems',
                'is_admin' => true,
            ],
            'cms' => [
                'title' => 'Content Management System (CMS)',
                'description' => 'Manage website articles, homepage, announcements, and media.',
                'path' => '/cms',
                'is_admin' => true,
            ],
            'donations' => [
                'title' => 'Donation Management System (DMS)',
                'description' => 'Track donations, fundraising campaigns, and donor records.',
                'path' => '/donations/admin',
                'is_admin' => true,
            ],
            'sponsorship' => [
                'title' => 'Sponsorship Management System (SPMS)',
                'description' => 'Sponsor partner portal, package management, and inquiries.',
                'path' => '/sponsorship/admin',
                'is_admin' => true,
            ],
            'store' => [
                'title' => 'MSA Store Management',
                'description' => 'Official merchandise catalog, inventory, and order fulfillment.',
                'path' => '/store/admin',
                'is_admin' => true,
            ],
            'mlibms' => [
                'title' => 'Library Management System (MLIBMS)',
                'description' => 'Library catalog search, book intake, and circulation control.',
                'path' => '/admin/systems/library',
                'is_admin' => true,
            ],
            'admin-portal' => [
                'title' => 'Admin Command Center',
                'description' => 'Global MSA platform administration, roles, and settings portal.',
                'path' => '/admin',
                'is_admin' => true,
            ],
        ];

        $results = [];
        $seenTitles = [];

        foreach ($rawAccess as $slug => $data) {
            if (!empty($data['access']) && isset($definitions[$slug])) {
                $def = $definitions[$slug];

                if (in_array($def['title'], $seenTitles, true)) {
                    continue;
                }
                $seenTitles[] = $def['title'];

                $results[] = [
                    'slug' => $slug,
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'path' => $def['path'],
                    'is_admin' => $def['is_admin'],
                    'source' => $data['source'],
                ];
            }
        }

        return $results;
    }
}
