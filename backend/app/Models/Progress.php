<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progress extends Model
{
    use HasFactory;

    protected $table = 'progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'completion_percentage',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'completion_percentage' => 'integer',
    ];

    /**
     * Get the user this progress belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the lesson.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
