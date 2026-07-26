<?php

namespace App\Ems\Services;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Support\EmsPermissions;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the EMS dashboard payload.
 *
 * Everything here is scoped to what the viewer may see, so an organizer's
 * counters describe their own events rather than the whole programme.
 *
 * Kept intentionally simple: counts, a short upcoming list and recent audit
 * activity. Phase 6 replaces this with real analytics; the endpoint shape is
 * designed so extra sections can be added without breaking the current UI.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        return [
            'summary' => $this->summary($user),
            'upcoming_events' => $this->upcomingEvents($user),
            'recent_activity' => $this->recentActivity($user),
        ];
    }

    /**
     * Status counters plus the two derived cards the dashboard shows.
     *
     * One grouped query rather than one query per card.
     *
     * @return array<string, int>
     */
    public function summary(User $user): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = Event::query()
            ->visibleTo($user)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $counts = [];

        foreach (EventStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        return array_merge([
            'total' => array_sum($counts),
            'upcoming' => Event::query()->visibleTo($user)->upcoming()->count(),
        ], $counts);
    }

    /**
     * @return Collection<int, Event>
     */
    public function upcomingEvents(User $user, ?int $limit = null): Collection
    {
        return Event::query()
            ->visibleTo($user)
            ->with(['category', 'organizer'])
            ->upcoming()
            ->orderBy('start_at')
            ->limit($limit ?? (int) config('ems.dashboard.upcoming_limit', 5))
            ->get();
    }

    /**
     * Recent EMS actions, read from the platform audit trail.
     *
     * Viewers without events.view_all only see activity on events they are
     * entitled to, so the feed never leaks the existence of other events.
     *
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(User $user, ?int $limit = null): Collection
    {
        $query = AuditLog::query()
            ->with('user:id,uuid,name,email')
            ->where('action', 'like', EmsActivityLogger::PREFIX . '%')
            ->latest('created_at')
            ->limit($limit ?? (int) config('ems.dashboard.activity_limit', 10));

        if (! $user->hasPermission(EmsPermissions::EVENTS_VIEW_ALL)) {
            $visibleEventIds = Event::query()
                ->visibleTo($user)
                ->toBase()
                ->pluck('id');

            $query->where(function (Builder $scoped) use ($visibleEventIds): void {
                $scoped
                    ->where(function (Builder $events) use ($visibleEventIds): void {
                        $events->where('target_type', Event::class)
                            ->whereIn('target_id', $visibleEventIds);
                    })
                    // Taxonomy changes are not event-specific and are safe to
                    // show to anyone who can read the EMS.
                    ->orWhere('target_type', \App\Ems\Models\EventCategory::class);
            });
        }

        return $query->get();
    }

    /**
     * Quick actions the viewer is permitted to take, resolved server-side so
     * the frontend never has to reimplement the permission mapping.
     *
     * @return array<int, array{key: string, label: string, route: string}>
     */
    public function quickActions(User $user): array
    {
        $candidates = [
            [
                'key' => 'create_event',
                'label' => 'Create Event',
                'route' => '/ems/events/create',
                'permission' => EmsPermissions::EVENTS_CREATE,
            ],
            [
                'key' => 'manage_events',
                'label' => 'Manage Events',
                'route' => '/ems/events',
                'permission' => EmsPermissions::EVENTS_VIEW,
            ],
            [
                'key' => 'manage_categories',
                'label' => 'Manage Categories',
                'route' => '/ems/categories',
                'permission' => EmsPermissions::CATEGORIES_VIEW,
            ],
        ];

        $allowed = array_filter(
            $candidates,
            fn (array $action): bool => $user->hasPermission($action['permission'])
        );

        return array_values(array_map(
            fn (array $action): array => [
                'key' => $action['key'],
                'label' => $action['label'],
                'route' => $action['route'],
            ],
            $allowed
        ));
    }
}
