<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'passed',
        'started_at',
        'submitted_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'integer',
    ];

    /**
     * Get the user who made the attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the quiz that was attempted.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the answers submitted in this attempt.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
