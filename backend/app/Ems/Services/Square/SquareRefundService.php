<?php

namespace App\Ems\Services\Square;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * EMS → Square refunds and Square → EMS refund synchronization.
 *
 * A refund is never marked completed solely because Square accepted the request.
 * EMS follows Square's PaymentRefund status: PENDING, COMPLETED, FAILED, REJECTED.
 */
class SquareRefundService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly TicketIssuer $tickets,
    ) {
    }

    public function refund(Payment $payment, ?float $amount, ?string $reason, ?User $actor = null): SquareRefund
    {
        $payment->loadMissing(['order', 'registration.tickets']);

        if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            throw new EmsException(
                'Only paid payments can be refunded.',
                ['payment' => ['This payment is not eligible for refund.']],
                Response::HTTP_CONFLICT
            );
        }

        if (! $payment->provider_payment_id) {
            throw new EmsException(
                'This payment has no Square payment id.',
                ['payment' => ['Cannot refund a payment that was never captured in Square.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $already = (float) $payment->amount_refunded;
        $remaining = round(((float) $payment->amount) - $already, 2);
        if ($remaining <= 0) {
            throw new EmsException(
                'This payment has already been fully refunded.',
                ['amount' => ['No remaining refundable amount.']],
                Response::HTTP_CONFLICT
            );
        }

        $requested = $amount === null ? $remaining : round($amount, 2);
        if ($requested <= 0 || $requested > $remaining) {
            throw new EmsException(
                'Refund amount is invalid.',
                ['amount' => ['Must be greater than 0 and at most ' . number_format($remaining, 2, '.', '') . '.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $pending = SquareRefund::query()
            ->where('payment_id', $payment->id)
            ->where('status', SquareRefundStatus::Pending->value)
            ->exists();
        if ($pending) {
            throw new EmsException(
                'A refund is already pending for this payment.',
                ['refund' => ['Wait for the pending Square refund to finish.']],
                Response::HTTP_CONFLICT
            );
        }

        $record = new SquareRefund();
        $record->payment_id = $payment->id;
        $record->order_id = $payment->order_id;
        $record->registration_id = $payment->registration_id;
        $record->initiated_by = $actor?->id;
        $record->idempotency_key = Str::limit('ems-rfnd-' . $payment->uuid . '-' . Str::lower(Str::random(8)), 45, '');
        $record->amount = number_format($requested, 2, '.', '');
        $record->currency = $payment->currency;
        $record->status = SquareRefundStatus::Pending;
        $record->reason = $reason ? Str::limit($reason, 192, '') : 'EMS refund';
        $record->save();

        try {
            $response = $this->square->post('/v2/refunds', [
                'idempotency_key' => $record->idempotency_key,
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => [
                    'amount' => (int) round($requested * 100),
                    'currency' => strtoupper($payment->currency),
                ],
                'reason' => $record->reason,
            ], $record->idempotency_key);
        } catch (\Throwable $e) {
            $record->status = SquareRefundStatus::Failed;
            $record->failure_reason = Str::limit($e->getMessage(), 500, '');
            $record->failed_at = now();
            $record->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.refund.request_failed', [
                    'payment_uuid' => $payment->uuid,
                    'ems_order_reference' => $payment->order?->reference,
                    'square_payment_id' => $payment->provider_payment_id,
                    'error' => $e->getMessage(),
                ]);

            throw new EmsException(
                'Square rejected the refund request.',
                ['refund' => [$e->getMessage()]],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $refund = is_array($response['refund'] ?? null) ? $response['refund'] : [];
        $this->applySquareRefund($record->fresh(), $refund);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.refund.requested', [
                'payment_uuid' => $payment->uuid,
                'ems_order_reference' => $payment->order?->reference,
                'square_payment_id' => $payment->provider_payment_id,
                'square_refund_id' => $record->fresh()->provider_refund_id,
                'amount' => $requested,
            ]);

        return $record->fresh(['payment']);
    }

    /**
     * @param  array<string, mixed>  $squareRefund
     */
    public function applyFromWebhook(array $squareRefund): ?SquareRefund
    {
        $refundId = (string) ($squareRefund['id'] ?? '');
        $paymentId = (string) ($squareRefund['payment_id'] ?? '');

        $record = null;
        if ($refundId !== '') {
            $record = SquareRefund::query()->where('provider_refund_id', $refundId)->first();
        }

        if ($record === null && $paymentId !== '') {
            $payment = Payment::query()->where('provider_payment_id', $paymentId)->first();
            if ($payment !== null) {
                $record = SquareRefund::query()
                    ->where('payment_id', $payment->id)
                    ->where(function ($query) use ($refundId, $squareRefund): void {
                        if ($refundId !== '') {
                            $query->where('provider_refund_id', $refundId);
                        }
                        $query->orWhere(function ($inner) use ($squareRefund): void {
                            $amount = isset($squareRefund['amount_money']['amount'])
                                ? number_format(((int) $squareRefund['amount_money']['amount']) / 100, 2, '.', '')
                                : null;
                            if ($amount !== null) {
                                $inner->where('amount', $amount)
                                    ->where('status', SquareRefundStatus::Pending->value);
                            }
                        });
                    })
                    ->latest('id')
                    ->first();

                if ($record === null) {
                    $record = $this->createFromSquare($payment, $squareRefund);
                }
            }
        }

        if ($record === null) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.refund.unmatched', [
                    'square_refund_id' => $refundId ?: null,
                    'square_payment_id' => $paymentId ?: null,
                ]);

            return null;
        }

        return $this->applySquareRefund($record, $squareRefund);
    }

    /**
     * @param  array<string, mixed>  $squareRefund
     */
    public function applySquareRefund(SquareRefund $record, array $squareRefund): SquareRefund
    {
        return DB::transaction(function () use ($record, $squareRefund): SquareRefund {
            /** @var SquareRefund $locked */
            $locked = SquareRefund::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if (! empty($squareRefund['id'])) {
                $locked->provider_refund_id = (string) $squareRefund['id'];
            }

            $raw = strtoupper((string) ($squareRefund['status'] ?? $locked->status->value));
            $status = match ($raw) {
                'COMPLETED' => SquareRefundStatus::Completed,
                'FAILED' => SquareRefundStatus::Failed,
                'REJECTED' => SquareRefundStatus::Rejected,
                default => SquareRefundStatus::Pending,
            };

            if ($locked->status === SquareRefundStatus::Completed && $status === SquareRefundStatus::Completed) {
                return $locked;
            }

            $locked->status = $status;
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'square_status' => $raw,
            ]);

            if ($status === SquareRefundStatus::Completed) {
                $locked->completed_at = now();
                $locked->failure_reason = null;
                $locked->save();
                $this->fulfillCompleted($locked);

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.refund.completed', [
                        'square_refund_id' => $locked->provider_refund_id,
                        'square_payment_id' => $locked->payment?->provider_payment_id,
                        'payment_uuid' => $locked->payment?->uuid,
                        'ems_order_reference' => $locked->order?->reference,
                    ]);
            } elseif (in_array($status, [SquareRefundStatus::Failed, SquareRefundStatus::Rejected], true)) {
                $locked->failed_at = now();
                $locked->failure_reason = (string) ($squareRefund['reason'] ?? $raw);
                $locked->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.refund.failed', [
                        'square_refund_id' => $locked->provider_refund_id,
                        'status' => $status->value,
                    ]);
            } else {
                $locked->save();
            }

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $squareRefund
     */
    private function createFromSquare(Payment $payment, array $squareRefund): SquareRefund
    {
        $amountMoney = $squareRefund['amount_money'] ?? [];
        $amount = isset($amountMoney['amount'])
            ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
            : '0.00';

        $record = new SquareRefund();
        $record->payment_id = $payment->id;
        $record->order_id = $payment->order_id;
        $record->registration_id = $payment->registration_id;
        $record->provider_refund_id = isset($squareRefund['id']) ? (string) $squareRefund['id'] : null;
        $record->idempotency_key = 'sq-' . ($squareRefund['id'] ?? Str::uuid());
        $record->amount = $amount;
        $record->currency = (string) ($amountMoney['currency'] ?? $payment->currency);
        $record->status = SquareRefundStatus::Pending;
        $record->reason = isset($squareRefund['reason']) ? Str::limit((string) $squareRefund['reason'], 192, '') : 'Square refund';
        $record->save();

        return $record;
    }

    private function fulfillCompleted(SquareRefund $refund): void
    {
        $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->first();
        if ($payment === null) {
            return;
        }

        $completed = (float) SquareRefund::query()
            ->where('payment_id', $payment->id)
            ->where('status', SquareRefundStatus::Completed->value)
            ->sum('amount');

        $payment->amount_refunded = number_format($completed, 2, '.', '');
        $full = $completed >= ((float) $payment->amount - 0.001);

        if ($full) {
            $payment->status = PaymentStatus::Refunded;
            $payment->refunded_at = now();
        } else {
            $payment->status = PaymentStatus::PartiallyRefunded;
        }
        $payment->save();

        $order = $payment->order;
        $registration = $payment->registration ?? $order?->registrations()->first();

        if ($full && $order !== null) {
            $order->status = OrderStatus::Refunded;
            $order->save();
        }

        if ($full && $registration instanceof Registration) {
            $registration->status = RegistrationStatus::Refunded;
            $registration->save();

            foreach ($registration->tickets as $ticket) {
                $this->tickets->revoke($ticket, 'Ticket refunded.');
            }
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.refunds.fulfillment', [
                'payment_uuid' => $payment->uuid,
                'full' => $full,
                'amount_refunded' => $payment->amount_refunded,
                'registration_uuid' => $registration?->uuid,
            ]);
    }
}
