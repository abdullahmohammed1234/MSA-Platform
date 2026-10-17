<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One logical volunteer digest period.
 *
 * pending -> sending -> sent | empty | failed (retryable) | permanently_failed
 */
class VolunteerDigestRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PERMANENTLY_FAILED = 'permanently_failed';

    public const CUTOFF_TIME = '23:55:00';
    public const MAX_ATTEMPTS = 5;
    public const LEASE_MINUTES = 30;

    protected $fillable = [
        'batch',
        'period_date',
        'cutoff_at',
        'status',
        'attempts',
        'registrations_count',
        'last_attempt_at',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'period_date' => 'date',
        'cutoff_at' => 'datetime',
        'attempts' => 'integer',
        'registrations_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * The most recent cutoff at or before $now. A late or missed scheduler run still
     * lands on the same period, so the assignment does not depend on when cron fires.
     */
    public static function latestDueCutoff(?Carbon $now = null): Carbon
    {
        $now ??= now();
        [$h, $m, $s] = array_map('intval', explode(':', self::CUTOFF_TIME));
        $cutoff = $now->copy()->setTime($h, $m, $s);

        return $cutoff->gt($now) ? $cutoff->subDay() : $cutoff;
    }

    /**
     * @param  Builder<VolunteerDigestRun>  $query
     * @return Builder<VolunteerDigestRun>
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', self::STATUS_PENDING)
                ->orWhere(function (Builder $q): void {
                    $q->where('status', self::STATUS_FAILED)->where('attempts', '<', self::MAX_ATTEMPTS);
                })
                ->orWhere(function (Builder $q): void {
                    $q->where('status', self::STATUS_SENDING)
                        ->where(function (Builder $q): void {
                            $q->whereNull('last_attempt_at')
                                ->orWhere('last_attempt_at', '<', now()->subMinutes(self::LEASE_MINUTES));
                        });
                });
        });
    }

    public static function claim(int $id): bool
    {
        $now = now();

        return static::query()
            ->whereKey($id)
            ->claimable()
            ->update([
                'status' => self::STATUS_SENDING,
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
                'last_attempt_at' => $now,
                'updated_at' => $now,
            ]) === 1;
    }
}
