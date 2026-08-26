<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DAMSCurrentUserResource;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DAMSAccessController extends Controller
{
    /**
     * GET /api/v1/dams/users/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'permissions']);

        return ApiResponse::success(
            new DAMSCurrentUserResource($user),
            'DAMS user retrieved successfully.'
        );
    }
}
