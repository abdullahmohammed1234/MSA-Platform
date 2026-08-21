<?php

namespace App\Ems\Models;

use App\Ems\Enums\OrderStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Database\Factories\Ems\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A purchase attempt for one or more tickets at an event.
 *
 * @property int $event_id
 * @property string $total_amount
 * @property OrderStatus $status
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_orders';

    protected $fillable = [
        'uuid',
        'reference',
        'event_id',
        'user_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'total_amount',
        'currency',
        'status',
        'source_channel',
        'provider_order_id',
        'completed_at',
        'cancelled_at',
        'failed_at',
        'metadata',
        'promo_code_id',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'status' => OrderStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'promo_code_id' => 'integer',
            'discount_amount' => 'decimal:2',
        ];
    }

    protected $attributes = [
        'status' => OrderStatus::Pending->value,
    ];

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'order_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id')->latestOfMany();
    }

    public function isFree(): bool
    {
        return (float) $this->total_amount === 0.0;
    }

    public function transitionTo(OrderStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        return true;
    }
}
