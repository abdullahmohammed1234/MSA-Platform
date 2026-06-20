<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Badge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'image_path',
        'criteria_type',
        'criteria_value',
    ];

    protected $casts = [
        'criteria_value' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($badge) {
            if (empty($badge->uuid)) {
                $badge->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the awards issued for this badge.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }
}
