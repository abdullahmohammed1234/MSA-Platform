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
        'is_merchandise',
        'inventory_quantity',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'has_variants' => 'boolean',
        'is_merchandise' => 'boolean',
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

    public function getMerchandiseImage(string $imageType, string $viewType): ?StoreProductImage
    {
        return $this->images->first(function (StoreProductImage $img) use ($imageType, $viewType) {
            return $img->image_type === $imageType && $img->view_type === $viewType;
        });
    }

    public function hasAllRequiredMerchandiseImages(): bool
    {
        if (! $this->is_merchandise) {
            return true;
        }

        $required = [
            ['sister', 'front'],
            ['sister', 'back'],
            ['brother', 'front'],
            ['brother', 'back'],
            ['neutral', 'front'],
            ['neutral', 'back'],
        ];

        foreach ($required as [$type, $view]) {
            if ($this->getMerchandiseImage($type, $view) === null) {
                return false;
            }
        }

        return true;
    }
}
