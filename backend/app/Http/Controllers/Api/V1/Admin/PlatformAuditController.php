<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAuditController extends Controller
{
    public function __construct(
        private PlatformAuditService $auditService
    ) {}

    /**
     * GET /api/v1/admin/platform/audit
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.audit') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.audit'], 403);
        }

        $filters = $request->only(['application', 'severity', 'action', 'user_id', 'search', 'start_date', 'end_date']);
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->auditService->searchLogs($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }
}
