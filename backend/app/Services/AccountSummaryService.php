<?php

namespace App\Services;

use App\Ems\Models\Registration as EmsRegistration;
use App\Models\CertificateAward;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Store\Models\StoreOrder;
use App\Volunteering\Models\Signup as VolunteeringSignup;
use App\Volunteering\Models\VolunteerProfile;

class AccountSummaryService
{
    protected ApplicationAccessService $appAccessService;

    public function __construct(ApplicationAccessService $appAccessService)
    {
        $this->appAccessService = $appAccessService;
    }

    /**
     * Build full unified account summary payload for authenticated user.
     */
    public function getSummary(User $user): array
    {
        $user->loadMissing(['roles']);

        // 1. Identity & Community Status
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
            'is_active' => (bool) $user->is_active,
            'email_verified' => !is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'member_since' => $user->created_at?->format('Y'),
            'created_at' => $user->created_at?->toIso8601String(),
            'community_status' => $communityStatus,
            'roles' => $this->formatPublicRoles($user->roles->pluck('slug')->toArray()),
        ];

        // 2. Application Access Grid
        $rawAccess = $this->appAccessService->accessibleApplications($user);
        $applications = $this->formatApplications($rawAccess);

        // 3. User-Scoped Bounded Activity & Counts
        $userId = $user->id;

        // EMS Tickets & Registrations (STRICTLY user_id = $userId, NO email matching)
        $upcomingEventsCount = EmsRegistration::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'awaiting_payment', 'waitlisted', 'pending'])
            ->whereHas('event', function ($query) {
                $query->where('start_at', '>=', now()->subHours(6));
            })
            ->count();

        $recentEmsRegistrations = EmsRegistration::query()
            ->where('user_id', $userId)
            ->with(['event.category', 'tickets', 'ticketType'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'uuid' => $reg->uuid,
                    'reference' => $reg->reference,
                    'status' => $reg->status->value ?? (string) $reg->status,
                    'event' => $reg->event ? [
                        'id' => $reg->event->id,
                        'title' => $reg->event->title,
                        'slug' => $reg->event->slug,
                        'start_at' => $reg->event->start_at?->toIso8601String(),
                        'location' => $reg->event->location_name ?? $reg->event->location,
                        'category' => $reg->event->category?->name,
                    ] : null,
                    'ticket_type' => $reg->ticketType ? [
                        'name' => $reg->ticketType->name,
                        'price' => $reg->ticketType->price,
                    ] : null,
                    'tickets_count' => $reg->tickets->count(),
                    'created_at' => $reg->created_at?->toIso8601String(),
                ];
            });

        // Store Orders (STRICTLY user_id = $userId)
        $storeOrdersCount = StoreOrder::query()
            ->where('user_id', $userId)
            ->count();

        $recentStoreOrders = StoreOrder::query()
            ->where('user_id', $userId)
            ->with(['items.product', 'items.variant'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'fulfillment_status' => $order->fulfillment_status,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency ?? 'CAD',
                    'items_count' => $order->items->sum('quantity'),
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            });

        // Volunteering Activity (STRICTLY user_id = $userId)
        $volunteerShiftsCount = VolunteeringSignup::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'confirmed'])
            ->count();

        $completedShiftsCount = VolunteeringSignup::query()
            ->where('user_id', $userId)
            ->where('attendance_status', 'attended')
            ->count();

        $volunteerProfile = VolunteerProfile::query()
            ->where('user_id', $userId)
            ->with(['skills', 'interests'])
            ->first();

        $volunteerSummary = null;
        if ($volunteerProfile || $user->hasRole('volunteer')) {
            $volunteerSummary = [
                'status' => $volunteerProfile ? 'active' : 'registered',
                'completion_percentage' => $volunteerProfile?->profile_completion_percentage ?? 0,
                'skills' => $volunteerProfile ? $volunteerProfile->skills->pluck('name')->toArray() : [],
                'completed_shifts_count' => $completedShiftsCount,
                'upcoming_shifts_count' => $volunteerShiftsCount,
            ];
        }

        // Academy Learning Summary (STRICTLY user_id = $userId)
        $enrolledCoursesCount = Enrollment::query()->where('user_id', $userId)->count();
        $completedCoursesCount = Enrollment::query()->where('user_id', $userId)->whereNotNull('completed_at')->count();
        $certificatesCount = CertificateAward::query()->where('user_id', $userId)->count();

        $recentCourses = Enrollment::query()
            ->where('user_id', $userId)
            ->with(['course'])
            ->orderBy('enrolled_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'status' => $enrollment->status,
                    'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
                    'completed_at' => $enrollment->completed_at?->toIso8601String(),
                    'course' => $enrollment->course ? [
                        'id' => $enrollment->course->id,
                        'title' => $enrollment->course->title,
                        'slug' => $enrollment->course->slug ?? null,
                    ] : null,
                ];
            });

        // Notifications
        $unreadNotificationsCount = Notification::query()
            ->where('user_id', $userId)
            ->unread()
            ->count();

        return [
            'user' => $userPayload,
            'applications' => $applications,
            'counts' => [
                'upcoming_events' => $upcomingEventsCount,
                'orders' => $storeOrdersCount,
                'volunteer_shifts' => $volunteerShiftsCount,
                'courses' => $enrolledCoursesCount,
                'unread_notifications' => $unreadNotificationsCount,
            ],
            'volunteer' => $volunteerSummary,
            'academy' => [
                'enrolled_courses' => $enrolledCoursesCount,
                'completed_courses' => $completedCoursesCount,
                'certificates' => $certificatesCount,
                'recent_courses' => $recentCourses,
            ],
            'activity' => [
                'events' => $recentEmsRegistrations,
                'orders' => $recentStoreOrders,
            ],
        ];
    }

    /**
     * Filter out raw staff role slugs for public presentation.
     */
    private function formatPublicRoles(array $roles): array
    {
        $publicMap = [
            'member' => 'Member',
            'volunteer' => 'Volunteer',
            'mentor' => 'Mentor',
            'admin' => 'Administrator',
            'super-admin' => 'Super Administrator',
        ];

        $output = [];
        foreach ($roles as $role) {
            if (isset($publicMap[$role])) {
                $output[] = $publicMap[$role];
            }
        }

        return empty($output) ? ['Member'] : $output;
    }

    /**
     * Map raw application keys to clean user-facing titles and paths.
     */
    private function formatApplications(array $rawAccess): array
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
            'dms' => [
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
            'spms' => [
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

                // Avoid duplicating VMS and volunteering
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
