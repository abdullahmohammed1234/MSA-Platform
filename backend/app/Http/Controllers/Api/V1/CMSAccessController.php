<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CMSCurrentUserResource;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CMSAccessController extends Controller
{
    /**
     * GET /api/v1/cms/users/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'permissions']);

        return ApiResponse::success(
            new CMSCurrentUserResource($user),
            'CMS user retrieved successfully.'
        );
    }
}
