<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'question_id',
        'answer',
        'is_correct',
        'points_awarded',
    ];

    protected $casts = [
        'answer' => 'array',
        'is_correct' => 'boolean',
        'points_awarded' => 'integer',
    ];

    /**
     * Get the quiz attempt that contains this answer.
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * Get the question this answer corresponds to.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
