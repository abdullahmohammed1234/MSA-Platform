<?php

namespace App\Store\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoreProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'product_id',
        'name',
        'sku',
        'price_override_cents',
        'inventory_quantity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_override_cents' => 'integer',
        'inventory_quantity' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreProductVariant $variant) {
            if (empty($variant->uuid)) {
                $variant->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function getEffectivePriceCentsAttribute(): int
    {
        return $this->price_override_cents ?? $this->product->price_cents;
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->effective_price_cents / 100, 2);
    }
}
