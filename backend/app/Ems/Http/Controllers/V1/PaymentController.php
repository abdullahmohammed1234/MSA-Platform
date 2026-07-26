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
            new PaymentResource($payment),
            'Payment retrieved successfully.'
        );
    }
}
