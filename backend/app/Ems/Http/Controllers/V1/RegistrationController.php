<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\Public\PublicRegistrationResource;
use App\Ems\Models\Registration;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegistrationController extends EmsController
{
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
}
