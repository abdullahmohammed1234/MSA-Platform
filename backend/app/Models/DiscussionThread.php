<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionThread extends Model
{
    protected $fillable = ['title', 'content', 'category_id', 'user_id'];

    /**
     * Get the category that the thread belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DiscussionCategory::class, 'category_id');
    }

    /**
     * Get the author user of the thread.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the posts/replies inside the thread.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'thread_id');
    }
}
