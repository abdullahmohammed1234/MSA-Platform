<?php

namespace App\Ems\Models;

use App\Ems\Enums\NotificationChannel;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The delivery ledger for outbound event communications.
 *
 * @property int|null $event_id
 * @property string $type
 * @property NotificationChannel $channel
 * @property NotificationStatus $status
 */
class EventNotification extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_notifications';

    protected $fillable = [
        'uuid',
        'event_id',
        'registration_id',
        'order_id',
        'payment_id',
        'ticket_id',
        'user_id',
        'recipient_email',
        'channel',
        'type',
        'template_key',
        'idempotency_key',
        'subject',
        'body',
        'status',
        'queue_status',
        'provider_message_id',
        'scheduled_at',
        'queued_at',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'alert_sent_at',
        'error',
        'retry_count',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'alert_sent_at' => 'datetime',
            'payload' => 'array',
            'retry_count' => 'integer',
        ];
    }

    protected $attributes = [
        'channel' => NotificationChannel::Mail->value,
        'status' => NotificationStatus::Pending->value,
        'retry_count' => 0,
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
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

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Notifications that are due to be dispatched.
     *
     * @param  Builder<EventNotification>  $query
     * @return Builder<EventNotification>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                NotificationStatus::Pending->value,
                NotificationStatus::Scheduled->value,
            ])
            ->where(function (Builder $q): void {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }

    public function markQueued(): void
    {
        $this->queue_status = 'queued';
        $this->queued_at = now();
        $this->save();
    }

    public function markSent(): void
    {
        $this->status = NotificationStatus::Sent;
        $this->queue_status = 'sent';
        $this->sent_at = now();
        $this->failed_at = null;
        $this->error = null;
        $this->last_attempt_at = now();
        $this->save();
    }

    public function markFailed(string $reason, bool $incrementRetry = true): void
    {
        if ($incrementRetry) {
            $this->retry_count = (int) $this->retry_count + 1;
        }

        $this->status = NotificationStatus::Failed;
        $this->queue_status = 'failed';
        $this->failed_at = now();
        $this->last_attempt_at = now();
        $this->error = mb_substr($reason, 0, 500);
        $this->save();
    }
}
