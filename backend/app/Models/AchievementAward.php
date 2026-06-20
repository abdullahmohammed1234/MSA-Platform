<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AchievementAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'achievement_id',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($award) {
            if (empty($award->uuid)) {
                $award->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user who unlocked the achievement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the achievement that was unlocked.
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
