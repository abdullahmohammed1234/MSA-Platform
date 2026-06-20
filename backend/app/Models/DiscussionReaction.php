<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionReaction extends Model
{
    protected $fillable = [
        'user_id',
        'thread_id',
        'post_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'post_id');
    }
}
