<?php

namespace App\Store\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StoreInventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'product_id',
        'variant_id',
        'previous_quantity',
        'new_quantity',
        'adjustment',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
        'adjustment' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreInventoryAdjustment $adj) {
            if (empty($adj->uuid)) {
                $adj->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
