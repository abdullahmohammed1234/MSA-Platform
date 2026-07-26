<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Services\SquareWebhookService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquareWebhookController extends EmsController
{
    public function __construct(
        private readonly SquareWebhookService $webhooks,
    ) {
    }

    /**
     * POST /api/v1/webhooks/square
     */
    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) $request->header(
            'X-Square-Hmacsha256-Signature',
            $request->header('x-square-hmacsha256-signature', '')
        );

        $notificationUrl = (string) config(
            'ems.payments.square.webhook_notification_url',
            $request->url()
        );

        $payment = $this->webhooks->handle(
            $request->getContent(),
            $signature,
            $notificationUrl
        );

        return ApiResponse::success(
            $payment ? ['payment_uuid' => $payment->uuid, 'status' => $payment->status->value] : null,
            'Webhook processed.'
        );
    }
}
