<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
    ];

    /**
     * Boot the model to handle automatic UUID creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($path) {
            if (empty($path->uuid)) {
                $path->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the courses that belong to the learning path.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
                    ->withPivot('order')
                    ->withTimestamps()
                    ->orderBy('pivot_order');
    }
}
