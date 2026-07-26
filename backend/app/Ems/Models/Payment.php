<?php

namespace App\Ems\Models;

use App\Ems\Enums\PaymentProvider;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment recorded against an order (and optionally a registration).
 *
 * Never persist card data, provider secrets or webhook signing keys on this
 * model; `metadata` is for the provider's non-sensitive response envelope.
 *
 * @property int|null $order_id
 * @property int|null $registration_id
 * @property string $amount
 * @property PaymentProvider $provider
 * @property PaymentStatus $status
 */
class Payment extends Model
{
    use HasEmsUuid, SoftDeletes;

    protected $table = 'ems_payments';

    protected $fillable = [
        'uuid',
        'registration_id',
        'order_id',
        'amount',
        'amount_refunded',
        'currency',
        'provider',
        'provider_payment_id',
        'provider_order_id',
        'provider_checkout_id',
        'provider_transaction_id',
        'status',
        'paid_at',
        'refunded_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => PaymentStatus::Pending->value,
        'provider' => PaymentProvider::Square->value,
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    public function transitionTo(PaymentStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        return true;
    }
}
