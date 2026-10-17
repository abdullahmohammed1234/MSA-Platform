<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public const RESEND_CLAIM_MINUTES = 10;

    /**
     * Atomically move a failed log to "resending". Exactly one caller wins; a
     * stale "resending" claim (crash before completion) becomes reclaimable.
     */
    public static function claimForResend(int $id): bool
    {
        return static::query()
            ->whereKey($id)
            ->where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhere(function ($stale) {
                        $stale->where('status', 'resending')
                            ->where('updated_at', '<', now()->subMinutes(self::RESEND_CLAIM_MINUTES));
                    });
            })
            ->update(['status' => 'resending', 'updated_at' => now()]) === 1;
    }

    /**
     * Get the user who received (or was targeted to receive) this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
