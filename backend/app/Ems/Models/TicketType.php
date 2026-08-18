<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use Database\Factories\Ems\TicketTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A price tier for an event.
 *
 * @property int $event_id
 * @property string $name
 * @property string $price
 * @property string $currency
 * @property int|null $quantity
 * @property int $quantity_sold
 * @property bool $is_active
 * @property bool $is_visible
 */
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_ticket_types';

    protected $fillable = [
        'uuid',
        'event_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity',
        'quantity_sold',
        'sales_start_at',
        'sales_end_at',
        'is_active',
        'is_visible',
        'max_per_order',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'quantity_sold' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'max_per_order' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected $attributes = [
        'is_active' => true,
        'is_visible' => true,
        'quantity_sold' => 0,
        'sort_order' => 0,
    ];

    protected static function newFactory(): TicketTypeFactory
    {
        return TicketTypeFactory::new();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'ticket_type_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_type_id');
    }

    public function squareCatalogMapping(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SquareCatalogMapping::class, 'ticket_type_id');
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    /**
     * Null quantity means unlimited within the event's own capacity.
     */
    public function remainingQuantity(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }

        return max(0, $this->quantity - $this->quantity_sold);
    }

    public function isSoldOut(): bool
    {
        $remaining = $this->remainingQuantity();

        return $remaining !== null && $remaining <= 0;
    }

    public function isOnSale(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();

        if (! $this->is_active || ! $this->is_visible) {
            return false;
        }

        if ($this->sales_start_at !== null && $at < $this->sales_start_at) {
            return false;
        }

        if ($this->sales_end_at !== null && $at > $this->sales_end_at) {
            return false;
        }

        return true;
    }

    public function hasAvailableQuantity(int $quantity = 1): bool
    {
        $remaining = $this->remainingQuantity();

        if ($remaining === null) {
            return true;
        }

        return $remaining >= $quantity;
    }

    /**
     * @param  Builder<TicketType>  $query
     * @return Builder<TicketType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<TicketType>  $query
     * @return Builder<TicketType>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * @param  Builder<TicketType>  $query
     * @return Builder<TicketType>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<TicketType>  $query
     * @return Builder<TicketType>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->active()->visible()->ordered();
    }
}
