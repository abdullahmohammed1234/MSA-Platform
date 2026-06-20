<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'thumbnail',
        'difficulty',
        'estimated_duration',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'estimated_duration' => 'integer',
    ];

    /**
     * Boot the model to handle automatic UUID creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($course) {
            if (empty($course->uuid)) {
                $course->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the creator of the course.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the modules of the course.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    /**
     * Get all lessons belonging to the course modules.
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * Get the enrollments for the course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the certificates issued for this course.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Get the quizzes for the course.
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Get the learning paths this course belongs to.
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')
                    ->withPivot('order')
                    ->withTimestamps();
    }
}
