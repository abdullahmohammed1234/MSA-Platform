<?php

namespace App\Ems\Services;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Exceptions\TicketNotValidException;
use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Public discovery, calendar and ticket lookup (Phase 2).
 *
 * Only returns events that are both `is_public` and in a publicly-visible
 * lifecycle state. Never surfaces draft, archived or administrative fields.
 */
class PublicEventService
{
    private const SORTABLE = ['start_at', 'name'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $sortBy = in_array($filters['sort_by'] ?? null, self::SORTABLE, true)
            ? $filters['sort_by']
            : 'start_at';

        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);

        return $this->baseQuery($filters)
            ->with(['category'])
            ->withSum(['registrations as occupied_seats' => fn (Builder $q) => $q->occupyingCapacity()], 'quantity')
            ->orderBy($sortBy, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Resolve a public event by slug, or null when it must stay hidden.
     */
    public function findBySlug(string $slug): ?Event
    {
        /** @var Event|null $event */
        $event = Event::query()
            ->publiclyDiscoverable()
            ->where('slug', $slug)
            ->with(['category', 'organizer', 'ticketTypes' => fn ($q) => $q->publiclyAvailable()])
            ->withSum(['registrations as occupied_seats' => fn (Builder $q) => $q->occupyingCapacity()], 'quantity')
            ->first();

        return $event;
    }

    /**
     * Calendar feed for a date window — lightweight rows only.
     * The window is an overlap filter: multi-day events that started before
     * `starts_after` still appear if they have not ended yet.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Event>
     */
    public function calendar(array $filters = []): Collection
    {
        $startsAfter = $filters['starts_after'] ?? null;
        $startsBefore = $filters['starts_before'] ?? null;

        return $this->baseQuery($filters)
            ->with(['category'])
            ->when($startsAfter || $startsBefore, function (Builder $q) use ($startsAfter, $startsBefore) {
                if ($startsAfter) {
                    $q->where(function (Builder $overlap) use ($startsAfter) {
                        $overlap->where('end_at', '>=', $startsAfter)
                            ->orWhere(function (Builder $noEnd) use ($startsAfter) {
                                $noEnd->whereNull('end_at')
                                    ->where('start_at', '>=', $startsAfter);
                            });
                    });
                }

                if ($startsBefore) {
                    $q->where('start_at', '<=', $startsBefore);
                }
            })
            ->orderBy('start_at')
            ->limit((int) config('ems.public.calendar_max_events', 500))
            ->get(['id', 'uuid', 'name', 'slug', 'category_id', 'start_at', 'end_at', 'timezone', 'status', 'location']);
    }

    /**
     * @return Collection<int, EventCategory>
     */
    public function categories(): Collection
    {
        return EventCategory::query()
            ->active()
            ->ordered()
            ->withCount([
                'events as public_events_count' => fn (Builder $q) => $q->publiclyDiscoverable(),
            ])
            ->get();
    }

    public function findTicketByCode(string $code): ?Ticket
    {
        return Ticket::query()
            ->where('code', strtoupper(trim($code)))
            ->with(['event.category', 'registration'])
            ->first();
    }

    /**
     * Validate a ticket without redeeming it (Phase 4 owns check-in).
     *
     * @return array{valid: bool, ticket: Ticket|null, reason: string|null}
     */
    public function validateTicket(string $code): array
    {
        $ticket = $this->findTicketByCode($code);

        if ($ticket === null) {
            throw TicketNotValidException::notFound();
        }

        if ($ticket->status !== TicketStatus::Issued) {
            throw TicketNotValidException::inactive($ticket->status->label());
        }

        return [
            'valid' => true,
            'ticket' => $ticket,
            'reason' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Event>
     */
    private function baseQuery(array $filters): Builder
    {
        return Event::query()
            ->publiclyDiscoverable()
            ->search($filters['search'] ?? null)
            ->when(
                isset($filters['category_id']),
                fn (Builder $q) => $q->where('category_id', $filters['category_id'])
            )
            ->when(
                ! empty($filters['category_slug']),
                fn (Builder $q) => $q->whereHas(
                    'category',
                    fn (Builder $c) => $c->where('slug', $filters['category_slug'])
                )
            )
            ->when(
                ! empty($filters['upcoming']),
                fn (Builder $q) => $q->upcoming()
            )
            ->when(
                ! empty($filters['past']),
                fn (Builder $q) => $q->past()
            )
            ->when(
                ! empty($filters['featured']),
                fn (Builder $q) => $q->featured()
            )
            ->when(
                ! empty($filters['registration_open']),
                fn (Builder $q) => $q->where('status', EventStatus::RegistrationOpen->value)
            )
            ->when(
                ! empty($filters['registration_closed']),
                fn (Builder $q) => $q->where('status', EventStatus::RegistrationClosed->value)
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn (Builder $q) => $q->withStatus($filters['status'])
            );
    }

    private function resolvePerPage(mixed $requested): int
    {
        $default = (int) config('ems.defaults.per_page', 15);
        $max = (int) config('ems.defaults.max_per_page', 100);
        $perPage = is_numeric($requested) ? (int) $requested : $default;

        return max(1, min($perPage, $max));
    }
}
