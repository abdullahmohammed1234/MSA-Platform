<?php

namespace App\Ems\Models;

use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareRefund extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_square_refunds';

    protected $fillable = [
        'uuid',
        'payment_id',
        'order_id',
        'registration_id',
        'initiated_by',
        'provider_refund_id',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'reason',
        'failure_reason',
        'metadata',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => SquareRefundStatus::class,
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => SquareRefundStatus::Pending->value,
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
