<?php

namespace App\Ems\Services;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Http\Resources\StaleCaptureResource;
use App\Ems\Models\Event;
use App\Ems\Models\Payment;
use App\Ems\Models\SquareRefund;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Support\EmsPermissions;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin operations for Square captures recorded after buyer-cancelled checkouts.
 */
class StaleCaptureService
{
    public function __construct(
        private readonly SquareRefundService $refunds,
        private readonly EmsActivityLogger $activity,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<StaleCaptureResource>, total: int}
     */
    public function list(User $user, array $filters = []): array
    {
        if (! $user->hasPermission(EmsPermissions::PAYMENTS_REFUND)) {
            throw new EmsException(
                'Unauthorized.',
                [],
                Response::HTTP_FORBIDDEN
            );
        }

        $records = $this->discoverForUser($user, $filters);
        $items = $records
            ->map(fn (array $row) => $this->toResource($row['payment'], $row['entry']))
            ->values()
            ->all();

        return ['items' => $items, 'total' => count($items)];
    }

    public function get(User $user, Payment $payment, string $squarePaymentId): StaleCaptureResource
    {
        $this->assertCanView($user, $payment);

        $entry = $this->requireStaleCaptureEntry($payment, $squarePaymentId);

        return $this->toResource($payment, $entry);
    }

    public function resolveWithoutRefund(
        User $user,
        Payment $payment,
        string $squarePaymentId,
        string $reason,
    ): StaleCaptureResource {
        $this->assertCanResolve($user, $payment);

        return DB::transaction(function () use ($user, $payment, $squarePaymentId, $reason): StaleCaptureResource {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['order.registrations', 'registration', 'order.event']);

            $entry = $this->requireStaleCaptureEntry($locked, $squarePaymentId);

            if ($locked->isStaleCaptureResolved($entry)) {
                $status = $locked->staleCaptureResolutionStatus($entry);
                if ($status === 'resolved_no_refund') {
                    return $this->toResource(
                        $locked->fresh(['order.registrations', 'registration']),
                        $locked->findStaleCaptureEntry($squarePaymentId) ?? $entry
                    );
                }

                throw new EmsException(
                    'This stale capture has already been resolved.',
                    ['stale_capture' => ['Resolution status: ' . $status]],
                    Response::HTTP_CONFLICT
                );
            }

            $locked->updateStaleCaptureEntry($squarePaymentId, function (array $row) use ($user, $reason): array {
                $row['resolution'] = [
                    'status' => 'resolved_no_refund',
                    'resolved_at' => now()->toIso8601String(),
                    'resolved_by_user_id' => $user->id,
                    'reason' => $reason,
                ];

                return $row;
            });
            $locked->save();

            $event = $locked->order?->event;

            $this->activity->log(
                'stale_capture.resolved_no_refund',
                $locked,
                'Stale capture resolved without refund.',
                [
                    'payment_uuid' => $locked->uuid,
                    'square_payment_id' => $squarePaymentId,
                    'square_order_id' => $entry['square_order_id'] ?? null,
                    'event_uuid' => $event?->uuid,
                    'amount' => (float) $locked->amount,
                    'currency' => $locked->currency,
                    'actor_user_id' => $user->id,
                    'reason' => $reason,
                    'source' => $entry['source'] ?? null,
                ]
            );

            return $this->toResource(
                $locked->fresh(['order.registrations', 'registration']),
                $locked->findStaleCaptureEntry($squarePaymentId) ?? $entry
            );
        });
    }

    public function refund(
        User $user,
        Payment $payment,
        string $squarePaymentId,
        ?float $amount,
        string $reason,
    ): array {
        $this->assertCanResolve($user, $payment);

        $refund = $this->refunds->refundStaleCapture(
            $payment,
            $squarePaymentId,
            $amount,
            $reason,
            $user
        );

        $this->activity->log(
            'stale_capture.refund_initiated',
            $payment->fresh(),
            'Stale capture refund submitted to Square.',
            [
                'payment_uuid' => $payment->uuid,
                'square_payment_id' => $squarePaymentId,
                'square_refund_uuid' => $refund->uuid,
                'amount' => (float) $refund->amount,
                'currency' => $refund->currency,
                'actor_user_id' => $user->id,
                'reason' => $reason,
            ]
        );

        $freshPayment = $payment->fresh(['order.registrations', 'registration']);
        $entry = $freshPayment?->findStaleCaptureEntry($squarePaymentId) ?? [];

        return [
            'refund' => [
                'uuid' => $refund->uuid,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'amount' => (float) $refund->amount,
                'currency' => $refund->currency,
                'provider_refund_id' => $refund->provider_refund_id,
                'reason' => $refund->reason,
            ],
            'stale_capture' => $this->toResource($freshPayment, $entry),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{payment: Payment, entry: array<string, mixed>}>
     */
    private function discoverForUser(User $user, array $filters): Collection
    {
        $resolution = isset($filters['resolution']) ? (string) $filters['resolution'] : null;
        $eventUuid = isset($filters['event_uuid']) ? (string) $filters['event_uuid'] : null;
        $source = isset($filters['source']) ? (string) $filters['source'] : null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $reportedFrom = isset($filters['reported_from']) ? (string) $filters['reported_from'] : null;
        $reportedTo = isset($filters['reported_to']) ? (string) $filters['reported_to'] : null;

        $payments = Payment::query()
            ->where('status', PaymentStatus::Cancelled->value)
            ->whereNotNull('metadata')
            ->with(['order.registrations', 'registration.tickets', 'order.event'])
            ->orderByDesc('updated_at')
            ->get();

        $rows = collect();

        foreach ($payments as $payment) {
            if (! $payment->wasBuyerCancelled()) {
                continue;
            }

            $event = $payment->order?->event;
            if (! $this->userCanViewEvent($user, $event, $payment)) {
                continue;
            }

            if ($eventUuid !== null && $eventUuid !== '' && ($event?->uuid !== $eventUuid)) {
                continue;
            }

            foreach ($payment->staleCaptureEntries() as $entry) {
                $status = $payment->staleCaptureResolutionStatus($entry);

                if ($resolution !== null && $resolution !== '' && $resolution !== 'all' && $status !== $resolution) {
                    continue;
                }

                if ($source !== null && $source !== '' && ($entry['source'] ?? null) !== $source) {
                    continue;
                }

                $reportedAt = (string) ($entry['reported_at'] ?? '');
                if ($reportedFrom !== null && $reportedFrom !== '' && $reportedAt !== '' && $reportedAt < $reportedFrom) {
                    continue;
                }
                if ($reportedTo !== null && $reportedTo !== '' && $reportedAt !== '' && $reportedAt > $reportedTo) {
                    continue;
                }

                if ($search !== '') {
                    $haystack = strtolower(implode(' ', array_filter([
                        $payment->uuid,
                        $payment->order?->reference,
                        $payment->order?->buyer_email,
                        $payment->registration?->attendee_email,
                        $payment->registration?->attendee_name,
                        $entry['square_payment_id'] ?? null,
                        $entry['square_order_id'] ?? null,
                        $event?->title,
                    ])));
                    if (! str_contains($haystack, strtolower($search))) {
                        continue;
                    }
                }

                $rows->push(['payment' => $payment, 'entry' => $entry]);
            }
        }

        return $rows->sortByDesc(fn (array $row) => (string) ($row['entry']['reported_at'] ?? ''))->values();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function toResource(Payment $payment, array $entry): StaleCaptureResource
    {
        $resolvedById = data_get($entry, 'resolution.resolved_by_user_id');
        $resolvedBy = is_numeric($resolvedById) ? User::query()->find((int) $resolvedById) : null;

        return new StaleCaptureResource($payment, $entry, $resolvedBy);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireStaleCaptureEntry(Payment $payment, string $squarePaymentId): array
    {
        if ($payment->status !== PaymentStatus::Cancelled || ! $payment->wasBuyerCancelled()) {
            throw new EmsException(
                'This payment is not a buyer-cancelled stale capture checkout.',
                ['payment' => ['Invalid stale capture payment state.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $entry = $payment->findStaleCaptureEntry($squarePaymentId);
        if ($entry === null) {
            throw new EmsException(
                'Stale capture not found for this payment.',
                ['square_payment_id' => ['No matching stale capture record exists.']],
                Response::HTTP_NOT_FOUND
            );
        }

        return $entry;
    }

    private function assertCanView(User $user, Payment $payment): void
    {
        $payment->loadMissing('order.event');
        $event = $payment->order?->event;

        if (! $user->hasPermission(EmsPermissions::PAYMENTS_REFUND)) {
            throw new EmsException(
                'Unauthorized.',
                [],
                Response::HTTP_FORBIDDEN
            );
        }

        if (! $this->userCanViewEvent($user, $event, $payment)) {
            throw new EmsException(
                'Unauthorized.',
                [],
                Response::HTTP_FORBIDDEN
            );
        }
    }

    private function assertCanResolve(User $user, Payment $payment): void
    {
        $this->assertCanView($user, $payment);

        $payment->loadMissing('order.event');
        $event = $payment->order?->event;

        if ($event !== null && ! $user->can('update', $event)) {
            throw new EmsException(
                'Unauthorized.',
                [],
                Response::HTTP_FORBIDDEN
            );
        }
    }

    private function userCanViewEvent(User $user, ?Event $event, Payment $payment): bool
    {
        if ($event === null) {
            return $user->hasPermission(EmsPermissions::EVENTS_VIEW_ALL);
        }

        return $user->can('view', $event);
    }
}
