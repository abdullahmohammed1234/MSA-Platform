<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
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
