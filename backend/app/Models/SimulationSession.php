<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationSession extends Model
{
    protected $fillable = [
        'user_id',
        'scenario_id',
        'scenario_title',
        'category',
        'difficulty',
        'character_name',
        'avatar_seed',
        'overall_score',
        'atmosphere_score',
        'transcript',
        'reflections',
        'is_bookmarked',
        'completed_at',
    ];

    protected $casts = [
        'transcript' => 'array',
        'reflections' => 'array',
        'is_bookmarked' => 'boolean',
        'completed_at' => 'datetime',
        'overall_score' => 'integer',
        'atmosphere_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
