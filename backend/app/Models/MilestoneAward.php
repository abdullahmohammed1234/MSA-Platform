<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MilestoneAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'milestone_id',
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
     * Get the user who earned the milestone.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the milestone that was awarded.
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }
}
