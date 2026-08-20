<?php

namespace App\Ems\Models;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Ems\Support\EmsPermissions;
use App\Models\User;
use Database\Factories\Ems\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The EMS event aggregate root.
 *
 * `status` is never mass-assigned. It only changes through
 * App\Ems\Services\EventLifecycleService, which validates the transition
 * against the EventTransition state machine.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $banner_url
 * @property int|null $category_id
 * @property int|null $organizer_id
 * @property string|null $location
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property string $timezone
 * @property int|null $capacity
 * @property bool $waitlist_enabled
 * @property int|null $max_tickets_per_order
 * @property int|null $max_registrations_per_attendee
 * @property \Illuminate\Support\Carbon|null $registration_deadline_at
 * @property EventStatus $status
 * @property bool $is_public
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_events';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'short_description',
        'description',
        'banner_url',
        'category_id',
        'organizer_id',
        'organizer_name',
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
        'created_by',
        'updated_by',
        'series_id',
        'views_count',
        'registrations_started_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'registration_open_at' => 'datetime',
            'registration_closed_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'registration_deadline_at' => 'datetime',
            'capacity' => 'integer',
            'waitlist_enabled' => 'boolean',
            'max_tickets_per_order' => 'integer',
            'max_registrations_per_attendee' => 'integer',
            'is_public' => 'boolean',
            'series_id' => 'integer',
            'views_count' => 'integer',
            'registrations_started_count' => 'integer',
        ];
    }

    /**
     * Mirrors the column defaults so a freshly created model reports its real
     * state without an extra round trip to the database.
     */
    protected $attributes = [
        'status' => EventStatus::Draft->value,
        'is_public' => false,
        'waitlist_enabled' => false,
    ];

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /**
     * @return BelongsTo<EventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * The accountable owner of the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Co-organizer assignment rows.
     *
     * @return HasMany<EventOrganizer, $this>
     */
    public function organizers(): HasMany
    {
        return $this->hasMany(EventOrganizer::class, 'event_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function organizerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ems_event_organizers', 'event_id', 'user_id')
            ->withPivot(['role', 'is_primary', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<EventStaff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(EventStaff::class, 'event_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ems_event_staff', 'event_id', 'user_id')
            ->withPivot(['role', 'notes', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'event_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    /**
     * @return HasMany<WaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'event_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'event_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id');
    }

    /**
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'event_id');
    }

    /**
     * @return HasMany<EventNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(EventNotification::class, 'event_id');
    }

    /**
     * @return HasMany<EventReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class, 'event_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'series_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class, 'event_id');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /**
     * Restrict a query to the events a user is entitled to see.
     *
     * Holders of events.view_all see everything. Everyone else is scoped to
     * the events they own, created, co-organize or are staffed on — which is
     * what keeps organizers and staff inside their own lane without any
     * role-name checks.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission(EmsPermissions::EVENTS_VIEW_ALL)) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('ems_events.organizer_id', $user->id)
                ->orWhere('ems_events.created_by', $user->id)
                ->orWhereHas('organizers', fn (Builder $q) => $q->where('user_id', $user->id))
                ->orWhereHas('staff', fn (Builder $q) => $q->where('user_id', $user->id));
        });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeWithStatus(Builder $query, EventStatus|string|null $status): Builder
    {
        if ($status === null) {
            return $query;
        }

        return $query->where('status', $status instanceof EventStatus ? $status->value : $status);
    }

    /**
     * Events that have not started yet and are not finished with.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now())
            ->whereNotIn('status', [
                EventStatus::Completed->value,
                EventStatus::Archived->value,
                EventStatus::Cancelled->value,
            ]);
    }

    /**
     * Events that may surface on public channels: flagged public and in a
     * publicly-visible lifecycle state. Draft and archived never qualify.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePubliclyDiscoverable(Builder $query): Builder
    {
        $visible = array_values(array_map(
            fn (EventStatus $status): string => $status->value,
            array_filter(
                EventStatus::cases(),
                fn (EventStatus $status): bool => $status->isPubliclyVisible()
            )
        ));

        return $query->where('is_public', true)
            ->whereIn('status', $visible);
    }

    /**
     * Events whose start time is in the past (or already completed).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('start_at', '<', now())
                ->orWhere('status', EventStatus::Completed->value);
        });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('ems_events.name', 'like', $like)
                ->orWhere('ems_events.short_description', 'like', $like)
                ->orWhere('ems_events.description', 'like', $like)
                ->orWhere('ems_events.location', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
        });
    }

    // -----------------------------------------------------------------
    // Domain helpers
    // -----------------------------------------------------------------

    /**
     * The transitions that are legal from this event's current state, before
     * the caller's permissions are taken into account.
     *
     * @return array<int, EventTransition>
     */
    public function availableTransitions(): array
    {
        return EventTransition::availableFrom($this->status);
    }

    public function canTransitionTo(EventStatus $target): bool
    {
        return EventTransition::between($this->status, $target) !== null;
    }

    /**
     * Whether the user is on this event's delivery team in any capacity.
     */
    public function hasTeamMember(User $user): bool
    {
        if ($this->organizer_id === $user->id || $this->created_by === $user->id) {
            return true;
        }

        return $this->organizers()->where('user_id', $user->id)->exists()
            || $this->staff()->where('user_id', $user->id)->exists();
    }

    /**
     * Registration is only accepted while the event sits in the open state
     * and before any configured registration deadline.
     */
    public function isAcceptingRegistrations(): bool
    {
        if ($this->status !== EventStatus::RegistrationOpen) {
            return false;
        }

        if ($this->registration_deadline_at !== null && now()->greaterThan($this->registration_deadline_at)) {
            return false;
        }

        return true;
    }

    /**
     * Whether this event may be shown on public discovery surfaces.
     */
    public function isPubliclyDiscoverable(): bool
    {
        return $this->is_public && $this->status->isPubliclyVisible();
    }

    /**
     * Seats already claimed by registrations that occupy capacity.
     */
    public function occupiedSeats(): int
    {
        return (int) $this->registrations()->occupyingCapacity()->sum('quantity');
    }

    /**
     * Remaining capacity, or null when the event is unlimited.
     */
    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->occupiedSeats());
    }

    /**
     * Whether the requested number of seats can still be taken.
     */
    public function hasAvailableCapacity(int $quantity = 1): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->remainingCapacity() >= $quantity;
    }

    public function isSoldOut(): bool
    {
        return $this->capacity !== null && $this->remainingCapacity() === 0;
    }

    /**
     * Whether the event is over, so remaining confirmed guests count as no-shows.
     *
     * Cancelled events are excluded: those guests did not skip a held event.
     */
    public function hasEnded(): bool
    {
        if ($this->status === EventStatus::Cancelled) {
            return false;
        }

        if (in_array($this->status, [EventStatus::Completed, EventStatus::Archived], true)) {
            return true;
        }

        if ($this->end_at !== null) {
            return $this->end_at->isPast();
        }

        if ($this->start_at === null) {
            return false;
        }

        return $this->start_at->copy()->endOfDay()->isPast();
    }
}
