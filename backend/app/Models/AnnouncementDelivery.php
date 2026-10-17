<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recipient/channel of an announcement dispatch.
 *
 * pending -> sending -> sent | failed (retryable, next_attempt_at) | permanently_failed | skipped
 */
class AnnouncementDelivery extends Model
{
    public const RECIPIENT_USER = 'user';
    public const RECIPIENT_SUBSCRIBER = 'subscriber';

    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH = 'push';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PERMANENTLY_FAILED = 'permanently_failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'dispatch_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'status',
        'attempts',
        'manual_retries',
        'next_attempt_at',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'manual_retries' => 'integer',
        'next_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(AnnouncementDispatch::class, 'dispatch_id');
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('announcements.delivery.max_attempts', 5));
    }

    public static function leaseSeconds(): int
    {
        return max(60, (int) config('announcements.delivery.sending_lease_seconds', 600));
    }

    public static function backoffAfter(int $attempts): Carbon
    {
        $schedule = (array) config('announcements.delivery.backoff_minutes', [1, 5, 15, 60]);
        $index = min(max(0, $attempts - 1), count($schedule) - 1);

        return now()->addMinutes((int) ($schedule[$index] ?? 60));
    }

    /**
     * Rows a worker may claim now: never-attempted, due retries, or abandoned claims.
     *
     * @param  Builder<AnnouncementDelivery>  $query
     * @return Builder<AnnouncementDelivery>
     */
    public function scopeClaimable(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $q) use ($now): void {
            $q->where('status', self::STATUS_PENDING)
                ->orWhere(function (Builder $q) use ($now): void {
                    $q->where('status', self::STATUS_FAILED)
                        ->where('attempts', '<', self::maxAttempts())
                        ->where(function (Builder $q) use ($now): void {
                            $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
                        });
                })
                ->orWhere(function (Builder $q) use ($now): void {
                    $q->where('status', self::STATUS_SENDING)
                        ->where(function (Builder $q) use ($now): void {
                            $q->whereNull('last_attempt_at')
                                ->orWhere('last_attempt_at', '<', $now->copy()->subSeconds(self::leaseSeconds()));
                        });
                });
        });
    }

    /**
     * Rows that still need work now or later (not terminal).
     *
     * @param  Builder<AnnouncementDelivery>  $query
     * @return Builder<AnnouncementDelivery>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENDING])
                ->orWhere(function (Builder $q): void {
                    $q->where('status', self::STATUS_FAILED)->where('attempts', '<', self::maxAttempts());
                });
        });
    }

    /**
     * Atomically take ownership of one row. Exactly one concurrent caller wins.
     */
    public static function claim(int $id): bool
    {
        $now = now();

        return static::query()
            ->whereKey($id)
            ->claimable()
            ->update([
                'status' => self::STATUS_SENDING,
                'last_attempt_at' => $now,
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]) === 1;
    }
}
