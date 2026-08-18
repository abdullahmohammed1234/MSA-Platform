<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\PaymentResource;
use App\Ems\Models\Payment;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentController extends EmsController
{
    /**
     * GET /api/v1/ems/payments/{payment}
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.event']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            throw new NotFoundHttpException('Payment not found.');
        }

        $this->authorize('view', $event);

        return ApiResponse::success(
            new PaymentResource($payment->loadMissing(['order.event', 'registration', 'squareRefunds'])),
            'Payment retrieved successfully.'
        );
    }

    public function refund(\App\Ems\Http\Requests\RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.event']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            throw new NotFoundHttpException('Payment not found.');
        }

        $this->authorize('update', $event);

        if (! $request->user()?->hasPermission(\App\Ems\Support\EmsPermissions::PAYMENTS_REFUND)) {
            abort(403, 'Unauthorized. Required permission: payments.refund');
        }

        $refund = app(\App\Ems\Services\Square\SquareRefundService::class)->refund(
            $payment,
            isset($request->validated()['amount']) ? (float) $request->validated()['amount'] : null,
            $request->validated()['reason'] ?? null,
            $request->user()
        );

        return ApiResponse::success(
            [
                'refund' => [
                    'uuid' => $refund->uuid,
                    'status' => $refund->status->value,
                    'status_label' => $refund->status->label(),
                    'amount' => (float) $refund->amount,
                    'currency' => $refund->currency,
                    'provider_refund_id' => $refund->provider_refund_id,
                    'reason' => $refund->reason,
                ],
                'payment' => new PaymentResource($payment->fresh(['order', 'squareRefunds'])),
            ],
            'Refund submitted to Square.'
        );
    }
}
