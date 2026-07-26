<?php

namespace App\Ems\Models;

use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Database\Factories\Ems\RegistrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An attendee's place at an event.
 *
 * The hinge of the flow: Registration -> TicketType -> Payment -> Ticket
 * -> CheckIn. Phase 2 opens free public registration; Phase 3 adds paid.
 *
 * @property int $event_id
 * @property int|null $user_id
 * @property string $reference
 * @property RegistrationStatus $status
 * @property RegistrationType $type
 */
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_registrations';

    protected $fillable = [
        'uuid',
        'reference',
        'event_id',
        'user_id',
        'ticket_type_id',
        'order_id',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'status',
        'type',
        'quantity',
        'waitlist_position',
        'amount_due',
        'currency',
        'registered_at',
        'confirmed_at',
        'cancelled_at',
        'notes',
        'metadata',
        'promo_code_id',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'type' => RegistrationType::class,
            'quantity' => 'integer',
            'waitlist_position' => 'integer',
            'amount_due' => 'decimal:2',
            'registered_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'promo_code_id' => 'integer',
            'discount_amount' => 'decimal:2',
        ];
    }

    protected $attributes = [
        'status' => RegistrationStatus::Pending->value,
        'type' => RegistrationType::Free->value,
    ];

    protected static function newFactory(): RegistrationFactory
    {
        return RegistrationFactory::new();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Null for guest registrations made without an MSA account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'registration_id');
    }

    /**
     * The payment that settled this registration, if any.
     *
     * @return HasOne<Payment, $this>
     */
    public function settledPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'registration_id')
            ->where('status', \App\Ems\Enums\PaymentStatus::Paid->value)
            ->latestOfMany();
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'registration_id');
    }

    /**
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'registration_id');
    }

    /**
     * @return HasMany<EventNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(EventNotification::class, 'registration_id');
    }

    /**
     * Registrations that count against the event's capacity.
     *
     * @param  Builder<Registration>  $query
     * @return Builder<Registration>
     */
    public function scopeOccupyingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (RegistrationStatus $status): string => $status->value,
            array_filter(
                RegistrationStatus::cases(),
                fn (RegistrationStatus $status): bool => $status->occupiesCapacity()
            )
        ));
    }
}
