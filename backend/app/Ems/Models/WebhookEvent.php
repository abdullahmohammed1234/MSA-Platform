<?php

namespace App\Ems\Models;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotency record for a provider webhook delivery.
 */
class WebhookEvent extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_webhook_events';

    protected $fillable = [
        'uuid',
        'provider',
        'event_id',
        'event_type',
        'status',
        'order_id',
        'payment_id',
        'payload',
        'failure_reason',
        'processed_at',
        'retry_count',
        'last_attempt_at',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public static function unmatchedMaxAttempts(): int
    {
        return max(1, (int) config('ems.payments.webhook_unmatched_max_attempts', 6));
    }

    public static function unmatchedBackoffAfter(int $attempts): \Illuminate\Support\Carbon
    {
        $schedule = array_values((array) config('ems.payments.webhook_unmatched_backoff_minutes', [5, 15, 60, 240, 720])) ?: [60];
        $minutes = (int) $schedule[min(max(0, $attempts - 1), count($schedule) - 1)];

        return now()->addMinutes(max(1, $minutes));
    }

    /**
     * Unmatched rows whose backoff has elapsed. Exhausted rows have already moved to
     * needs_review, so this never selects them.
     */
    public function scopeUnmatchedDue(Builder $query): Builder
    {
        return $query->where('status', WebhookEventStatus::Unmatched->value)
            ->where(function (Builder $due): void {
                $due->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Operator-initiated reprocessing. Conditional so it cannot steal a row that a
     * worker is processing or that has since been processed.
     */
    public static function requeueForOperator(int $id): bool
    {
        return static::query()
            ->whereKey($id)
            ->whereIn('status', WebhookEventStatus::operatorRequeueable())
            ->update([
                'status' => WebhookEventStatus::RetryPending->value,
                'retry_count' => 0,
                'next_retry_at' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    public static function processingLeaseSeconds(): int
    {
        return max(30, (int) config('ems.payments.webhook_processing_lease_seconds', 300));
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('ems.payments.webhook_max_attempts', 5));
    }

    /**
     * Atomically take ownership of a row for processing. Only one caller wins
     * per row; a "processing" row is reclaimable once its lease has expired.
     */
    public static function claimForProcessing(int $id): bool
    {
        $now = now();
        $staleBefore = $now->copy()->subSeconds(self::processingLeaseSeconds());

        return static::query()
            ->whereKey($id)
            ->where(function (Builder $query) use ($staleBefore): void {
                $query->whereIn('status', [
                    WebhookEventStatus::Received->value,
                    WebhookEventStatus::Failed->value,
                    WebhookEventStatus::RetryPending->value,
                    WebhookEventStatus::Unmatched->value,
                ])->orWhere(function (Builder $processing) use ($staleBefore): void {
                    $processing->where('status', WebhookEventStatus::Processing->value)
                        ->where(function (Builder $lease) use ($staleBefore): void {
                            $lease->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', $staleBefore);
                        });
                });
            })
            ->update([
                'status' => WebhookEventStatus::Processing->value,
                'last_attempt_at' => $now,
                'next_retry_at' => null,
                'retry_count' => DB::raw('retry_count + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    public function isProcessingLeaseHeld(): bool
    {
        return $this->status === WebhookEventStatus::Processing->value
            && $this->last_attempt_at !== null
            && $this->last_attempt_at->gt(now()->subSeconds(self::processingLeaseSeconds()));
    }

    /**
     * Rows that need an automatic retry: crashed mid-processing, received but
     * never processed, or failed with attempts remaining.
     */
    public function scopeAwaitingRecovery(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('retry_count', '<', self::maxAttempts())
            ->where(function (Builder $q) use ($now): void {
                $q->where(function (Builder $processing) use ($now): void {
                    $processing->where('status', WebhookEventStatus::Processing->value)
                        ->where(function (Builder $lease) use ($now): void {
                            $lease->whereNull('last_attempt_at')
                                ->orWhere('last_attempt_at', '<', $now->copy()->subSeconds(self::processingLeaseSeconds()));
                        });
                })->orWhere(function (Builder $received) use ($now): void {
                    $received->where('status', WebhookEventStatus::Received->value)
                        ->where('created_at', '<', $now->copy()->subMinutes(max(1, (int) config('ems.payments.webhook_recovery_received_grace_minutes', 5))));
                })->orWhere('status', WebhookEventStatus::RetryPending->value)
                ->orWhere(function (Builder $failed) use ($now): void {
                    $failed->where('status', WebhookEventStatus::Failed->value)
                        ->where('last_attempt_at', '<', $now->copy()->subMinutes(max(1, (int) config('ems.payments.webhook_recovery_failed_after_minutes', 60))));
                });
            });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
