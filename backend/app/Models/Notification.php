<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user who owns this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }
}
