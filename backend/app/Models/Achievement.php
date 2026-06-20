<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Achievement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'type', // completion, performance, participation, special_recognition
        'points',
        'criteria_type', // course_completed, quiz_passed, score_reached, lessons_count, courses_count, path_completed, manual
        'criteria_value',
    ];

    protected $casts = [
        'criteria_value' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($achievement) {
            if (empty($achievement->uuid)) {
                $achievement->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the awards issued for this achievement.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(AchievementAward::class);
    }
}
