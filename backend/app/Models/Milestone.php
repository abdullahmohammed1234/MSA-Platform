<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Milestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'type', // courses_completed, lessons_completed, paths_completed
        'threshold',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($milestone) {
            if (empty($milestone->uuid)) {
                $milestone->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the awards issued for this milestone.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(MilestoneAward::class);
    }
}
