<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\RefundStaleCaptureRequest;
use App\Ems\Http\Requests\ResolveStaleCaptureRequest;
use App\Ems\Http\Resources\StaleCaptureResource;
use App\Ems\Models\Payment;
use App\Ems\Services\StaleCaptureService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaleCaptureController extends EmsController
{
    public function __construct(
        private readonly StaleCaptureService $staleCaptures,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->staleCaptures->list($request->user(), $request->query());

        return ApiResponse::success(
            collect($result['items'])->map(fn (StaleCaptureResource $resource) => $resource->resolve($request))->values(),
            'Stale captures retrieved successfully.',
            ['total' => $result['total']]
        );
    }

    public function show(Request $request, Payment $payment, string $squarePaymentId): JsonResponse
    {
        $resource = $this->staleCaptures->get($request->user(), $payment, $squarePaymentId);

        return ApiResponse::success(
            $resource,
            'Stale capture retrieved successfully.'
        );
    }

    public function refund(RefundStaleCaptureRequest $request, Payment $payment, string $squarePaymentId): JsonResponse
    {
        $validated = $request->validated();
        $payload = $this->staleCaptures->refund(
            $request->user(),
            $payment,
            $squarePaymentId,
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            (string) $validated['reason']
        );

        return ApiResponse::success(
            $payload,
            'Stale capture refund submitted to Square.'
        );
    }

    public function resolve(ResolveStaleCaptureRequest $request, Payment $payment, string $squarePaymentId): JsonResponse
    {
        $resource = $this->staleCaptures->resolveWithoutRefund(
            $request->user(),
            $payment,
            $squarePaymentId,
            (string) $request->validated()['reason']
        );

        return ApiResponse::success(
            $resource,
            'Stale capture resolved without refund.'
        );
    }
}
