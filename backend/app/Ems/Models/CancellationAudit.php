<?php

namespace App\Ems\Models;

use App\Ems\Enums\RefundStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log record for registration cancellations and admin refunds.
 *
 * @property int $id
 * @property string $uuid
 * @property int $registration_id
 * @property int $event_id
 * @property int|null $ticket_type_id
 * @property string|null $ticket_type_name
 * @property string|null $attendee_name
 * @property string|null $attendee_email
 * @property string $initiator_type
 * @property int|null $user_id
 * @property string|null $reason
 * @property bool $refund_requested
 * @property float|null $refund_amount
 * @property RefundStatus $refund_status
 * @property string|null $provider_refund_id
 * @property int|null $square_refund_id
 * @property \Illuminate\Support\Carbon $cancelled_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property array|null $metadata
 */
class CancellationAudit extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_cancellation_audits';

    protected $fillable = [
        'uuid',
        'registration_id',
        'event_id',
        'ticket_type_id',
        'ticket_type_name',
        'attendee_name',
        'attendee_email',
        'initiator_type',
        'user_id',
        'reason',
        'refund_requested',
        'refund_amount',
        'refund_status',
        'provider_refund_id',
        'square_refund_id',
        'cancelled_at',
        'refunded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'refund_requested' => 'boolean',
            'refund_amount' => 'decimal:2',
            'refund_status' => RefundStatus::class,
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function squareRefund(): BelongsTo
    {
        return $this->belongsTo(SquareRefund::class, 'square_refund_id');
    }
}

