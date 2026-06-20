<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BadgeAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'badge_id',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
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
     * Get the user who was awarded the badge.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the badge that was awarded.
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
