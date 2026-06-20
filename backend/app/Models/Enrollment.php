<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user enrolled in the course.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course enrolled in.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
