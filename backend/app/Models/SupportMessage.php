<?php

namespace App\Models;

use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'inquiry_id',
        'user_id',
        'is_staff_reply',
        'message',
        'read_at',
    ];

    protected $appends = [
        'body',
        'sender_name',
    ];

    protected function casts(): array
    {
        return [
            'is_staff_reply' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function getBodyAttribute(): ?string
    {
        return $this->message;
    }

    public function getSenderNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(SupportInquiry::class, 'inquiry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
