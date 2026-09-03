<?php

namespace App\Store\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StoreCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreCartItem $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(StoreCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }

    public function getUnitPriceCentsAttribute(): int
    {
        if ($this->variant_id && $this->variant) {
            return $this->variant->effective_price_cents;
        }

        return $this->product?->price_cents ?? 0;
    }

    public function getLineTotalCentsAttribute(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }
}
