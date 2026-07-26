<?php

namespace App\Ems\Services;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Events\EventCreated;
use App\Ems\Events\EventDeleted;
use App\Ems\Events\EventUpdated;
use App\Ems\Models\Event;
use App\Ems\Services\Notifications\EventUpdateNotifier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Event CRUD business logic.
 *
 * Controllers stay thin: they validate, authorize and delegate here. Status is
 * never written by this service — EventLifecycleService owns that.
 */
class EventService
{
    /**
     * Sortable columns, allow-listed so a client cannot order by an arbitrary
     * column and probe the schema.
     */
    private const SORTABLE = ['start_at', 'name', 'status', 'created_at', 'updated_at'];

    public function __construct(
        private readonly EventUpdateNotifier $updateNotifier,
    ) {
    }

    /**
     * List events the user may see, with filtering, sorting and pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    public function paginate(?User $user, array $filters = []): LengthAwarePaginator
    {
        $sortBy = in_array($filters['sort_by'] ?? null, self::SORTABLE, true)
            ? $filters['sort_by']
            : 'start_at';

        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);

        return Event::query()
            ->visibleTo($user)
            ->with(['category', 'organizer'])
            ->withStatus($filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->when(
                isset($filters['category_id']),
                fn (Builder $query) => $query->where('category_id', $filters['category_id'])
            )
            ->when(
                isset($filters['organizer_id']),
                fn (Builder $query) => $query->where('organizer_id', $filters['organizer_id'])
            )
            ->when(
                ! empty($filters['upcoming']),
                fn (Builder $query) => $query->upcoming()
            )
            ->when(
                isset($filters['starts_after']),
                fn (Builder $query) => $query->where('start_at', '>=', $filters['starts_after'])
            )
            ->when(
                isset($filters['starts_before']),
                fn (Builder $query) => $query->where('start_at', '<=', $filters['starts_before'])
            )
            ->orderBy($sortBy, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Event
    {
        $event = DB::transaction(function () use ($data, $actor): Event {
            $event = new Event();

            $event->fill($this->attributesFrom($data));
            $event->slug = $this->uniqueSlug($data['slug'] ?? $data['name']);
            $event->timezone = $data['timezone'] ?? config('ems.defaults.timezone');

            // New events always start in draft; status only moves through the
            // lifecycle service.
            $event->status = EventStatus::Draft;

            // Default the accountable owner to whoever created the event, so
            // an organizer's own event is always in their scope.
            $event->organizer_id = $data['organizer_id'] ?? $actor->id;
            $event->created_by = $actor->id;
            $event->updated_by = $actor->id;

            $event->save();

            return $event;
        });

        $event->load(['category', 'organizer']);

        EventCreated::dispatch($event, $actor);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data, User $actor): Event
    {
        $audience = NotificationAudience::tryFrom((string) ($data['notify_audience'] ?? NotificationAudience::None->value))
            ?? NotificationAudience::None;
        unset($data['notify_audience']);

        DB::transaction(function () use ($event, $data, $actor): void {
            $event->fill($this->attributesFrom($data));

            if (array_key_exists('slug', $data) && filled($data['slug']) && $data['slug'] !== $event->slug) {
                $event->slug = $this->uniqueSlug($data['slug'], $event->id);
            }

            $event->updated_by = $actor->id;
            $event->save();
        });

        // Audit which fields actually moved, not which ones were submitted.
        $changed = array_values(array_diff(
            array_keys($event->getChanges()),
            ['updated_at', 'updated_by']
        ));

        $event->load(['category', 'organizer']);

        EventUpdated::dispatch($event, $actor, ['changed' => $changed]);

        if ($changed !== []) {
            $this->updateNotifier->handle($event, $changed, $audience);
        }

        return $event;
    }

    public function delete(Event $event, User $actor): void
    {
        // Dispatch before the delete so the listener still sees a loaded model.
        EventDeleted::dispatch($event, $actor);

        $event->updated_by = $actor->id;
        $event->saveQuietly();
        $event->delete();
    }

    /**
     * Only the fields a client may set. Status and lifecycle timestamps are
     * absent by design.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name',
            'short_description',
            'description',
            'banner_url',
            'category_id',
            'organizer_id',
            'location',
            'start_at',
            'end_at',
            'timezone',
            'capacity',
            'waitlist_enabled',
            'max_tickets_per_order',
            'max_registrations_per_attendee',
            'registration_deadline_at',
            'is_public',
        ]));
    }

    /**
     * Build a slug that is unique across live and soft-deleted events, so a
     * restored event can never collide with a newer one.
     */
    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'event';
        $base = Str::limit($base, 180, '');
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        return Event::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function resolvePerPage(mixed $requested): int
    {
        $default = (int) config('ems.defaults.per_page', 15);
        $max = (int) config('ems.defaults.max_per_page', 100);

        $perPage = is_numeric($requested) ? (int) $requested : $default;

        return max(1, min($perPage, $max));
    }
}
