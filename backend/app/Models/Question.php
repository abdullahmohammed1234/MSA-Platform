<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'type',
        'category',
        'difficulty',
        'tags',
        'question',
        'options',
        'correct_answer',
        'points',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'tags' => 'array',
        'correct_answer' => 'encrypted:array', // encrypt correct answers in the database
        'points' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Get the quiz that owns the question.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get user answers for this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
