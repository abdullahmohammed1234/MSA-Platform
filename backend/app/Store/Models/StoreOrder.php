<?php

namespace App\Store\Models;

use App\Models\User;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Enums\StorePaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoreOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'payment_status',
        'fulfillment_status',
        'square_payment_id',
        'square_order_id',
        'square_checkout_url',
        'notes',
        'paid_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'payment_status' => StorePaymentStatus::class,
        'fulfillment_status' => StoreFulfillmentStatus::class,
        'subtotal_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreOrder $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->order_number)) {
                $order->order_number = 'MS-' . date('Y') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class, 'order_id');
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_cents / 100, 2);
    }
}
