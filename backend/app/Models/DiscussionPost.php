<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionPost extends Model
{
    protected $fillable = ['content', 'thread_id', 'user_id', 'parent_id'];

    /**
     * Get the thread that the post belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    /**
     * Get the author user of the post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent post if this is a nested reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'parent_id');
    }

    /**
     * Get child replies for this post.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'parent_id');
    }
}
