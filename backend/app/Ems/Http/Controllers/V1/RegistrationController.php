<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\CancelRegistrationRequest;
use App\Ems\Http\Resources\CancellationAuditResource;
use App\Ems\Http\Resources\Public\PublicRegistrationResource;
use App\Ems\Models\Registration;
use App\Ems\Services\RegistrationCancellationService;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegistrationController extends EmsController
{
    public function __construct(
        private readonly RegistrationCancellationService $cancellationService,
    ) {
    }

    /**
     * GET /api/v1/ems/registrations/{registration}
     */
    public function show(Registration $registration): JsonResponse
    {
        $registration->loadMissing(['event', 'tickets.event.category', 'ticketType', 'order']);
        $this->authorize('view', $registration->event);

        return ApiResponse::success(
            new PublicRegistrationResource($registration),
            'Registration retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/registrations/{registration}/cancel
     */
    public function cancel(CancelRegistrationRequest $request, Registration $registration): JsonResponse
    {
        $registration->loadMissing(['event', 'ticketType', 'order.latestPayment', 'settledPayment']);
        $event = $registration->event;

        if ($event === null) {
            throw new NotFoundHttpException('Event not found.');
        }

        $this->authorize('cancelRegistration', $event);

        $validated = $request->validated();
        $issueRefund = (bool) ($validated['issue_refund'] ?? false);

        if ($issueRefund && ! $request->user()?->hasPermission(EmsPermissions::PAYMENTS_REFUND)) {
            abort(403, 'Unauthorized. Required permission: payments.refund to issue a refund.');
        }

        $result = $this->cancellationService->cancelByAdmin(
            $registration,
            $request->user(),
            (string) $validated['reason'],
            $issueRefund,
            isset($validated['refund_amount']) ? (float) $validated['refund_amount'] : null
        );

        $message = $result['refund_message'] ?? 'Registration cancelled successfully.';

        return ApiResponse::success([
            'registration' => new PublicRegistrationResource($result['registration']),
            'audit' => new CancellationAuditResource($result['audit']),
            'refund_status' => $result['refund_status'],
            'refund_message' => $result['refund_message'],
        ], $message);
    }

    /**
     * GET /api/v1/ems/registrations/{registration}/cancellation-history
     */
    public function cancellationHistory(Registration $registration): JsonResponse
    {
        $registration->loadMissing('event');
        $event = $registration->event;

        if ($event === null) {
            throw new NotFoundHttpException('Event not found.');
        }

        $this->authorize('viewCancellationHistory', $event);

        $history = $this->cancellationService->getRegistrationCancellationHistory($registration);

        return ApiResponse::success(
            CancellationAuditResource::collection($history),
            'Registration cancellation history retrieved.'
        );
    }
}
