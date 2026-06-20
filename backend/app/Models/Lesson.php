<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'slug',
        'content',
        'video_url',
        'attachments',
        'order',
        'estimated_duration',
        'is_required',
    ];

    protected $casts = [
        'attachments' => 'array',
        'order' => 'integer',
        'estimated_duration' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Get the module that owns the lesson.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get all progress records for this lesson.
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(Progress::class);
    }
}
