<?php

namespace App\Store\Models;

use App\Store\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoreProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'price_cents',
        'currency',
        'sku',
        'status',
        'has_variants',
        'inventory_quantity',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'has_variants' => 'boolean',
        'price_cents' => 'integer',
        'inventory_quantity' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreProduct $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(StoreProductImage::class, 'product_id')->orderBy('display_order');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(StoreInventoryAdjustment::class, 'product_id');
    }

    public function primaryImage(): ?StoreProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price_cents / 100, 2);
    }
}
