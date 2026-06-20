<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'passing_score',
        'time_limit',
        'attempt_limit',
        'status',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
        'attempt_limit' => 'integer',
    ];

    /**
     * Get the course that owns the quiz.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the questions for the quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    /**
     * Get attempts for this quiz.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
