<?php

namespace App\Ems\Services\Square;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\TicketType;
use App\Ems\Services\EmsActivityLogger;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * EMS → Square refunds and Square → EMS refund synchronization.
 *
 * A refund is never marked completed solely because Square accepted the request.
 * EMS follows Square's PaymentRefund status: PENDING, COMPLETED, FAILED, REJECTED.
 *
 * Each ems_square_refunds row is one logical refund operation. Its Square
 * idempotency key is derived from the row UUID (`ems-rfnd-{uuid}`) so HTTP
 * timeouts retry the same Square operation instead of creating a second refund.
 */
class SquareRefundService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly TicketIssuer $tickets,
        private readonly EventCommunicationService $communications,
        private readonly EmsActivityLogger $activity,
    ) {
    }

    public function refundStaleCapture(
        Payment $payment,
        string $squarePaymentId,
        ?float $amount,
        string $reason,
        ?User $actor = null,
    ): SquareRefund {
        $squarePaymentId = trim($squarePaymentId);
        if ($squarePaymentId === '') {
            throw new EmsException(
                'Square payment id is required.',
                ['square_payment_id' => ['A Square payment id is required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $squarePayment = $this->retrieveSquarePaymentById($squarePaymentId);
        $capturedAmount = $this->squarePaymentCapturedAmount($squarePayment);
        $currency = strtoupper((string) data_get($squarePayment, 'amount_money.currency', $payment->currency));

        $record = DB::transaction(function () use (
            $payment,
            $squarePaymentId,
            $amount,
            $reason,
            $actor,
            $capturedAmount,
            $currency,
        ): SquareRefund {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->loadMissing(['order', 'registration']);

            if ($lockedPayment->status !== PaymentStatus::Cancelled || ! $lockedPayment->wasBuyerCancelled()) {
                throw new EmsException(
                    'Only buyer-cancelled payments can receive stale capture refunds.',
                    ['payment' => ['Invalid payment state for stale capture refund.']],
                    Response::HTTP_CONFLICT
                );
            }

            $entry = $lockedPayment->findStaleCaptureEntry($squarePaymentId);
            if ($entry === null) {
                throw new EmsException(
                    'Stale capture not found for this payment.',
                    ['square_payment_id' => ['No matching stale capture record exists.']],
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($lockedPayment->isStaleCaptureResolved($entry)) {
                $status = $lockedPayment->staleCaptureResolutionStatus($entry);
                if ($status !== 'partially_refunded') {
                    throw new EmsException(
                        'This stale capture has already been resolved.',
                        ['stale_capture' => ['Resolution status: ' . $status]],
                        Response::HTTP_CONFLICT
                    );
                }
            }

            $alreadyRefunded = $this->completedStaleCaptureRefundTotal($lockedPayment, $squarePaymentId);
            $remaining = round($capturedAmount - $alreadyRefunded, 2);
            if ($remaining <= 0) {
                throw new EmsException(
                    'This stale capture has already been fully refunded.',
                    ['amount' => ['No remaining refundable amount.']],
                    Response::HTTP_CONFLICT
                );
            }

            $emsLimit = round(min($capturedAmount, (float) $lockedPayment->amount), 2);
            $remaining = round(min($remaining, $emsLimit - $alreadyRefunded), 2);
            if ($remaining <= 0) {
                throw new EmsException(
                    'This stale capture has already been fully refunded.',
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
                ->where('payment_id', $lockedPayment->id)
                ->where('status', SquareRefundStatus::Pending->value)
                ->where('metadata->stale_capture', true)
                ->where('metadata->stale_capture_square_payment_id', $squarePaymentId)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($pending !== null) {
                if ($pending->provider_refund_id
                    || round((float) $pending->amount, 2) !== $requested
                ) {
                    throw new EmsException(
                        'A stale capture refund is already pending.',
                        ['refund' => ['Wait for the pending Square refund to finish.']],
                        Response::HTTP_CONFLICT
                    );
                }

                return $pending;
            }

            $record = new SquareRefund();
            $record->uuid = (string) Str::uuid();
            $record->payment_id = $lockedPayment->id;
            $record->order_id = $lockedPayment->order_id;
            $record->registration_id = $lockedPayment->registration_id;
            $record->initiated_by = $actor?->id;
            $record->idempotency_key = $this->idempotencyKeyFor($record->uuid);
            $record->amount = number_format($requested, 2, '.', '');
            $record->currency = $currency;
            $record->status = SquareRefundStatus::Pending;
            $record->reason = Str::limit($reason, 192, '');
            $record->metadata = [
                'stale_capture' => true,
                'stale_capture_square_payment_id' => $squarePaymentId,
                'captured_amount' => number_format($capturedAmount, 2, '.', ''),
            ];
            $record->save();

            return $record;
        });

        return $this->submitStaleCaptureToSquare($record, $squarePaymentId, $currency);
    }

    public function refund(Payment $payment, ?float $amount, ?string $reason, ?User $actor = null): SquareRefund
    {
        $record = DB::transaction(function () use ($payment, $amount, $reason, $actor): SquareRefund {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->loadMissing(['order', 'registration.tickets']);

            if (! in_array($lockedPayment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw new EmsException(
                    'Only paid payments can be refunded.',
                    ['payment' => ['This payment is not eligible for refund.']],
                    Response::HTTP_CONFLICT
                );
            }

            if (! $lockedPayment->provider_payment_id) {
                throw new EmsException(
                    'This payment has no Square payment id.',
                    ['payment' => ['Cannot refund a payment that was never captured in Square.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $already = (float) $lockedPayment->amount_refunded;
            $remaining = round(((float) $lockedPayment->amount) - $already, 2);
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
                ->where('payment_id', $lockedPayment->id)
                ->where('status', SquareRefundStatus::Pending->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($pending !== null) {
                if ($pending->provider_refund_id
                    || round((float) $pending->amount, 2) !== $requested
                ) {
                    throw new EmsException(
                        'A refund is already pending for this payment.',
                        ['refund' => ['Wait for the pending Square refund to finish.']],
                        Response::HTTP_CONFLICT
                    );
                }

                return $pending;
            }

            $record = new SquareRefund();
            $record->uuid = (string) Str::uuid();
            $record->payment_id = $lockedPayment->id;
            $record->order_id = $lockedPayment->order_id;
            $record->registration_id = $lockedPayment->registration_id;
            $record->initiated_by = $actor?->id;
            $record->idempotency_key = $this->idempotencyKeyFor($record->uuid);
            $record->amount = number_format($requested, 2, '.', '');
            $record->currency = $lockedPayment->currency;
            $record->status = SquareRefundStatus::Pending;
            $record->reason = $reason ? Str::limit($reason, 192, '') : 'EMS refund';
            $record->save();

            return $record;
        });

        return $this->submitToSquare($record, $payment->fresh(['order', 'registration']));
    }

    /**
     * Apply a Square PaymentRefund object from a webhook or reconciliation.
     *
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
            $record = SquareRefund::query()
                ->where('metadata->stale_capture', true)
                ->where('metadata->stale_capture_square_payment_id', $paymentId)
                ->whereIn('status', [SquareRefundStatus::Pending->value, SquareRefundStatus::Completed->value])
                ->when($refundId !== '', fn ($query) => $query->where(function ($inner) use ($refundId, $squareRefund): void {
                    $inner->where('provider_refund_id', $refundId)
                        ->orWhere(function ($pending) use ($squareRefund): void {
                            $amount = isset($squareRefund['amount_money']['amount'])
                                ? number_format(((int) $squareRefund['amount_money']['amount']) / 100, 2, '.', '')
                                : null;
                            if ($amount !== null) {
                                $pending->where('amount', $amount)
                                    ->where('status', SquareRefundStatus::Pending->value)
                                    ->whereNull('provider_refund_id');
                            }
                        });
                }))
                ->latest('id')
                ->first();

            if ($record === null) {
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
                                        ->where('status', SquareRefundStatus::Pending->value)
                                        ->whereNull('provider_refund_id');
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
            $previous = $locked->status;

            if (! empty($squareRefund['id'])) {
                $locked->provider_refund_id = (string) $squareRefund['id'];
            }

            $raw = strtoupper((string) ($squareRefund['status'] ?? $locked->status->value));
            $incoming = SquareRefundStatus::fromSquare($raw, $locked->status);

            if (! $previous->canAdvanceTo($incoming)) {
                $locked->metadata = array_merge($locked->metadata ?? [], [
                    'square_status' => $raw,
                ]);
                $locked->save();

                if ($previous !== $incoming) {
                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->info('ems.square.refund.stale_status_ignored', [
                            'square_refund_id' => $locked->provider_refund_id,
                            'stored_status' => $previous->value,
                            'incoming_status' => $incoming->value,
                        ]);
                }

                return $locked->fresh();
            }

            $locked->status = $incoming;
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'square_status' => $raw,
            ]);

            if ($incoming === SquareRefundStatus::Completed) {
                $locked->completed_at = now();
                $locked->failure_reason = null;
                $locked->save();

                if ((bool) data_get($locked->metadata, 'stale_capture', false)) {
                    $this->fulfillStaleCaptureCompleted($locked);
                } else {
                    $this->fulfillCompleted($locked);
                }

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.refund.completed', [
                        'square_refund_id' => $locked->provider_refund_id,
                        'square_payment_id' => data_get($locked->metadata, 'stale_capture_square_payment_id')
                            ?? $locked->payment?->provider_payment_id,
                        'payment_uuid' => $locked->payment?->uuid,
                        'ems_order_reference' => $locked->order?->reference,
                        'stale_capture' => (bool) data_get($locked->metadata, 'stale_capture', false),
                    ]);
            } elseif (in_array($incoming, [SquareRefundStatus::Failed, SquareRefundStatus::Rejected], true)) {
                $locked->failed_at = now();
                $locked->failure_reason = (string) ($squareRefund['reason'] ?? $raw);
                $locked->save();

                if ((bool) data_get($locked->metadata, 'stale_capture', false)) {
                    $this->activity->log(
                        'stale_capture.refund_failed',
                        $locked->payment,
                        'Stale capture refund failed.',
                        [
                            'payment_uuid' => $locked->payment?->uuid,
                            'square_payment_id' => data_get($locked->metadata, 'stale_capture_square_payment_id'),
                            'square_refund_uuid' => $locked->uuid,
                            'failure_reason' => $locked->failure_reason,
                        ],
                        EmsActivityLogger::RESULT_FAILED,
                        'warning'
                    );
                }

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.refund.failed', [
                        'square_refund_id' => $locked->provider_refund_id,
                        'status' => $incoming->value,
                    ]);
            } else {
                $locked->save();
            }

            $fresh = $locked->fresh();
            if (! (bool) data_get($fresh->metadata, 'stale_capture', false)) {
                $this->queueRefundNotification($fresh, $previous, $incoming);
            }

            return $fresh;
        });
    }

    public static function idempotencyKeyForUuid(string $uuid): string
    {
        return 'ems-rfnd-' . $uuid;
    }

    private function idempotencyKeyFor(string $uuid): string
    {
        return self::idempotencyKeyForUuid($uuid);
    }

    private function submitToSquare(SquareRefund $record, Payment $payment): SquareRefund
    {
        $requested = (float) $record->amount;

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
        } catch (EmsException $e) {
            $uncertain = $e->status() >= 500;
            $this->rememberSubmitFailure($record, $e->getMessage(), $uncertain);
            if (! $uncertain) {
                $this->queueRefundNotification(
                    $record->fresh(),
                    SquareRefundStatus::Pending,
                    SquareRefundStatus::Failed
                );
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.refund.request_failed', [
                    'payment_uuid' => $payment->uuid,
                    'ems_order_reference' => $payment->order?->reference,
                    'square_payment_id' => $payment->provider_payment_id,
                    'ems_refund_uuid' => $record->uuid,
                    'error' => $e->getMessage(),
                ]);

            throw new EmsException(
                'Square rejected the refund request.',
                ['refund' => [$e->getMessage()]],
                $uncertain ? Response::HTTP_BAD_GATEWAY : Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $e) {
            $this->rememberSubmitFailure($record, $e->getMessage(), true);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.refund.request_failed', [
                    'payment_uuid' => $payment->uuid,
                    'ems_order_reference' => $payment->order?->reference,
                    'square_payment_id' => $payment->provider_payment_id,
                    'ems_refund_uuid' => $record->uuid,
                    'error' => $e->getMessage(),
                ]);

            throw new EmsException(
                'Square rejected the refund request.',
                ['refund' => [$e->getMessage()]],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $refund = is_array($response['refund'] ?? null) ? $response['refund'] : [];
        $applied = $this->applySquareRefund($record->fresh(), $refund);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.refund.requested', [
                'payment_uuid' => $payment->uuid,
                'ems_order_reference' => $payment->order?->reference,
                'square_payment_id' => $payment->provider_payment_id,
                'square_refund_id' => $applied->provider_refund_id,
                'amount' => $requested,
            ]);

        return $applied->fresh(['payment']);
    }

    private function submitStaleCaptureToSquare(
        SquareRefund $record,
        string $squarePaymentId,
        string $currency
    ): SquareRefund {
        $requested = (float) $record->amount;

        try {
            $response = $this->square->post('/v2/refunds', [
                'idempotency_key' => $record->idempotency_key,
                'payment_id' => $squarePaymentId,
                'amount_money' => [
                    'amount' => (int) round($requested * 100),
                    'currency' => strtoupper($currency),
                ],
                'reason' => $record->reason,
            ], $record->idempotency_key);
        } catch (EmsException $e) {
            $uncertain = $e->status() >= 500;
            $this->rememberSubmitFailure($record, $e->getMessage(), $uncertain);

            throw new EmsException(
                'Square rejected the stale capture refund request.',
                ['refund' => [$e->getMessage()]],
                $uncertain ? Response::HTTP_BAD_GATEWAY : Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $e) {
            $this->rememberSubmitFailure($record, $e->getMessage(), true);

            throw new EmsException(
                'Square rejected the stale capture refund request.',
                ['refund' => [$e->getMessage()]],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $refund = is_array($response['refund'] ?? null) ? $response['refund'] : [];
        $applied = $this->applySquareRefund($record->fresh(), $refund);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.refund.stale_capture_requested', [
                'payment_uuid' => $applied->payment?->uuid,
                'square_payment_id' => $squarePaymentId,
                'square_refund_id' => $applied->provider_refund_id,
                'amount' => $requested,
            ]);

        return $applied->fresh(['payment']);
    }

    private function rememberSubmitFailure(SquareRefund $record, string $message, bool $uncertain): void
    {
        $record->failure_reason = Str::limit($message, 500, '');

        if ($uncertain) {
            $record->save();

            return;
        }

        $record->status = SquareRefundStatus::Failed;
        $record->failed_at = now();
        $record->save();

        if ((bool) data_get($record->metadata, 'stale_capture', false)) {
            $record->loadMissing('payment');

            $this->activity->log(
                'stale_capture.refund_failed',
                $record->payment,
                'Stale capture refund failed.',
                [
                    'payment_uuid' => $record->payment?->uuid,
                    'square_payment_id' => data_get($record->metadata, 'stale_capture_square_payment_id'),
                    'square_refund_uuid' => $record->uuid,
                    'amount' => (float) $record->amount,
                    'currency' => $record->currency,
                    'actor_user_id' => $record->initiated_by,
                    'failure_reason' => $record->failure_reason,
                ],
                EmsActivityLogger::RESULT_FAILED,
                'warning'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $squareRefund
     */
    private function createFromSquare(Payment $payment, array $squareRefund): SquareRefund
    {
        $refundId = isset($squareRefund['id']) ? (string) $squareRefund['id'] : '';
        $amountMoney = $squareRefund['amount_money'] ?? [];
        $amount = isset($amountMoney['amount'])
            ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
            : '0.00';

        $record = new SquareRefund();
        $record->payment_id = $payment->id;
        $record->order_id = $payment->order_id;
        $record->registration_id = $payment->registration_id;
        $record->provider_refund_id = $refundId !== '' ? $refundId : null;
        $record->idempotency_key = Str::limit('sq-' . ($refundId !== '' ? $refundId : (string) Str::uuid()), 64, '');
        $record->amount = $amount;
        $record->currency = (string) ($amountMoney['currency'] ?? $payment->currency);
        $record->status = SquareRefundStatus::Pending;
        $record->reason = isset($squareRefund['reason']) ? Str::limit((string) $squareRefund['reason'], 192, '') : 'Square refund';

        try {
            $record->save();
        } catch (QueryException $e) {
            if ($refundId !== '') {
                $existing = SquareRefund::query()->where('provider_refund_id', $refundId)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        return $record;
    }

    private function fulfillCompleted(SquareRefund $refund): void
    {
        $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->first();
        if ($payment === null) {
            return;
        }

        if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.refunds.fulfillment_skipped_invalid_payment_status', [
                    'payment_uuid' => $payment->uuid,
                    'payment_status' => $payment->status->value,
                    'square_refund_uuid' => $refund->uuid,
                    'provider_refund_id' => $refund->provider_refund_id,
                ]);

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
        $registrations = $this->registrationsForPayment($payment);

        if ($full && $order !== null) {
            $order->status = OrderStatus::Refunded;
            $order->save();
        }

        $inventoryReleased = (bool) data_get($payment->metadata, 'refund_inventory_released', false);

        if ($full) {
            foreach ($registrations as $registration) {
                $registration->status = RegistrationStatus::Refunded;
                $registration->save();

                $registration->loadMissing('tickets');
                foreach ($registration->tickets as $ticket) {
                    $this->tickets->revoke($ticket, 'Ticket refunded.');
                }

                if (! $inventoryReleased) {
                    $this->restoreTicketTypeInventory($registration);
                }
            }

            if (! $inventoryReleased) {
                $metadata = $payment->metadata ?? [];
                $metadata['refund_inventory_released'] = true;
                $payment->metadata = $metadata;
                $payment->save();
            }
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.refunds.fulfillment', [
                'payment_uuid' => $payment->uuid,
                'full' => $full,
                'amount_refunded' => $payment->amount_refunded,
                'registration_uuids' => $registrations->pluck('uuid')->all(),
            ]);
    }

    private function fulfillStaleCaptureCompleted(SquareRefund $refund): void
    {
        $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->first();
        if ($payment === null || $payment->status !== PaymentStatus::Cancelled) {
            return;
        }

        $squarePaymentId = (string) data_get($refund->metadata, 'stale_capture_square_payment_id');
        if ($squarePaymentId === '') {
            return;
        }

        $capturedAmount = (float) data_get($refund->metadata, 'captured_amount', $payment->amount);
        $completedTotal = $this->completedStaleCaptureRefundTotal($payment, $squarePaymentId);
        $remaining = round(max(0, $capturedAmount - $completedTotal), 2);
        $resolutionStatus = $remaining <= 0.001 ? 'refunded' : 'partially_refunded';

        $payment->updateStaleCaptureEntry($squarePaymentId, function (array $row) use (
            $refund,
            $resolutionStatus,
            $completedTotal,
            $remaining
        ): array {
            $row['resolution'] = [
                'status' => $resolutionStatus,
                'resolved_at' => now()->toIso8601String(),
                'resolved_by_user_id' => $refund->initiated_by,
                'reason' => $refund->reason,
                'square_refund_uuid' => $refund->uuid,
                'amount_refunded' => number_format($completedTotal, 2, '.', ''),
                'remaining_refundable_amount' => number_format($remaining, 2, '.', ''),
            ];

            return $row;
        });
        $payment->save();

        $this->activity->log(
            'stale_capture.refund_completed',
            $payment,
            'Stale capture refund completed.',
            [
                'payment_uuid' => $payment->uuid,
                'square_payment_id' => $squarePaymentId,
                'square_refund_uuid' => $refund->uuid,
                'provider_refund_id' => $refund->provider_refund_id,
                'amount' => (float) $refund->amount,
                'currency' => $refund->currency,
                'resolution_status' => $resolutionStatus,
            ]
        );

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.refunds.stale_capture_fulfillment', [
                'payment_uuid' => $payment->uuid,
                'square_payment_id' => $squarePaymentId,
                'amount_refunded' => $completedTotal,
                'resolution_status' => $resolutionStatus,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function retrieveSquarePaymentById(string $squarePaymentId): array
    {
        $response = $this->square->get('/v2/payments/' . urlencode($squarePaymentId));
        $object = $response['payment'] ?? [];
        if (! is_array($object) || $object === []) {
            throw new EmsException(
                'Square payment could not be retrieved.',
                ['square_payment_id' => ['Unable to verify Square payment state.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $rawStatus = strtoupper((string) ($object['status'] ?? ''));
        if (! in_array($rawStatus, ['COMPLETED', 'APPROVED', 'PAID'], true)) {
            throw new EmsException(
                'Square payment is not captured and cannot be refunded.',
                ['square_payment_id' => ['Square reported status: ' . ($rawStatus ?: 'UNKNOWN')]],
                Response::HTTP_CONFLICT
            );
        }

        return $object;
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    private function squarePaymentCapturedAmount(array $squarePayment): float
    {
        $amountMoney = $squarePayment['amount_money'] ?? [];

        return isset($amountMoney['amount'])
            ? round(((int) $amountMoney['amount']) / 100, 2)
            : 0.0;
    }

    private function completedStaleCaptureRefundTotal(Payment $payment, string $squarePaymentId): float
    {
        return (float) SquareRefund::query()
            ->where('payment_id', $payment->id)
            ->where('metadata->stale_capture', true)
            ->where('metadata->stale_capture_square_payment_id', $squarePaymentId)
            ->where('status', SquareRefundStatus::Completed->value)
            ->sum('amount');
    }

    /**
     * A payment belongs to one order. Checkout, POS, and Terminal each create
     * one registration per order today; fulfillment still updates every
     * registration on that order so a multi-registration order cannot leave
     * sibling tickets valid after a full refund.
     *
     * @return \Illuminate\Support\Collection<int, Registration>
     */
    private function registrationsForPayment(Payment $payment): \Illuminate\Support\Collection
    {
        if ($payment->order_id) {
            return Registration::query()
                ->where('order_id', $payment->order_id)
                ->lockForUpdate()
                ->get();
        }

        if ($payment->registration_id) {
            $registration = Registration::query()
                ->whereKey($payment->registration_id)
                ->lockForUpdate()
                ->first();

            return collect($registration ? [$registration] : []);
        }

        return collect();
    }

    private function restoreTicketTypeInventory(Registration $registration): void
    {
        if (! $registration->ticket_type_id) {
            return;
        }

        $ticketType = TicketType::query()
            ->whereKey($registration->ticket_type_id)
            ->lockForUpdate()
            ->first();

        if ($ticketType === null) {
            return;
        }

        $ticketType->quantity_sold = max(0, (int) $ticketType->quantity_sold - (int) $registration->quantity);
        $ticketType->save();
    }

    private function queueRefundNotification(
        ?SquareRefund $refund,
        SquareRefundStatus $previous,
        SquareRefundStatus $incoming
    ): void {
        if ($refund === null || $previous === $incoming) {
            return;
        }

        $type = match ($incoming) {
            SquareRefundStatus::Completed => NotificationType::RefundCompleted,
            SquareRefundStatus::Failed, SquareRefundStatus::Rejected => NotificationType::RefundFailed,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $refundId = $refund->id;
        DB::afterCommit(function () use ($refundId, $type): void {
            try {
                $record = SquareRefund::query()->with(['payment', 'registration.event'])->find($refundId);
                $registration = $record?->registration
                    ?? $record?->payment?->registration
                    ?? ($record?->payment?->order_id
                        ? Registration::query()->where('order_id', $record->payment->order_id)->first()
                        : null);

                if ($record === null || $registration === null) {
                    return;
                }

                $this->communications->sendRefund(
                    $registration,
                    $type,
                    (float) $record->amount,
                    $record->payment,
                    $record->uuid
                );
            } catch (\Throwable $e) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.refund.notify_failed', [
                        'refund_id' => $refundId,
                        'error' => $e->getMessage(),
                    ]);
            }
        });
    }
}
