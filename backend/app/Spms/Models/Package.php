<?php

namespace App\Spms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Package extends Model
{
    use HasFactory;

    protected $table = 'spms_packages';

    protected $fillable = [
        'uuid',
        'opportunity_id',
        'name',
        'description',
        'price_cents',
        'max_available',
        'claimed_count',
        'is_customizable',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'max_available' => 'integer',
        'claimed_count' => 'integer',
        'is_customizable' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(PackageBenefit::class, 'package_id');
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'package_id');
    }
}
