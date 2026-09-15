<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformCommandCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCommandCenterController extends Controller
{
    public function __construct(
        private PlatformCommandCenterService $commandCenterService
    ) {}

    private function checkPermission(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('platform.view') && ! $user->hasPermission('system.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized access to Platform Command Center.'], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/command-center
     * Unified executive Command Center telemetry, priority attention items, application matrix, business & automation snapshots, and deterministic risk score.
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $period = (string) $request->input('period', '30d');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $data = $this->commandCenterService->getCommandCenterData(
            $request->user(),
            $period,
            $startDate,
            $endDate
        );

        return response()->json(array_merge([
            'success' => true,
        ], $data));
    }
}
