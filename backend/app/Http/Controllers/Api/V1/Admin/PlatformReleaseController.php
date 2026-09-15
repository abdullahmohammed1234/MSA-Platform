<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformRelease;
use App\Services\Lifecycle\DeploymentReadinessService;
use App\Services\Release\ChangeImpactAnalysisService;
use App\Services\Release\PlatformChangeService;
use App\Services\Release\PlatformReleaseService;
use App\Services\Release\PostReleaseVerificationService;
use App\Services\Release\ReleaseCorrelationService;
use App\Services\Release\RollbackReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PlatformReleaseController extends Controller
{
    public function __construct(
        private PlatformReleaseService $releaseService,
        private PlatformChangeService $changeService,
        private ChangeImpactAnalysisService $impactService,
        private PostReleaseVerificationService $verificationService,
        private ReleaseCorrelationService $correlationService,
        private RollbackReadinessService $rollbackService,
        private DeploymentReadinessService $readinessService
    ) {}

    /**
     * GET /api/v1/admin/releases
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $filters = $request->only(['status', 'environment', 'search']);
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->releaseService->listReleases($filters, $perPage, $user);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/releases/current
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $overview = $this->releaseService->getActiveRelease();

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);

        return response()->json([
            'success' => true,
            'data' => $release,
        ]);
    }

    /**
     * POST /api/v1/admin/releases
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.manage') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.manage'], 403);
        }

        $validated = $request->validate([
            'release_identifier' => 'nullable|string|unique:platform_releases,release_identifier',
            'application_version' => 'required|string',
            'api_version' => 'nullable|string',
            'frontend_build' => 'nullable|string',
            'environment' => 'nullable|string',
            'release_notes' => 'nullable|string',
            'affected_applications' => 'nullable|array',
        ]);

        $release = $this->releaseService->createRelease($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Release registered successfully.',
            'data' => $release,
        ], 201);
    }

    /**
     * POST /api/v1/admin/releases/{identifier}/approve
     */
    public function approve(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.approve') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.approve'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $notes = (string) $request->input('release_notes', '');

        try {
            $approvedRelease = $this->releaseService->approveRelease($release, $user, $notes);

            return response()->json([
                'success' => true,
                'message' => 'Release approved successfully.',
                'data' => $approvedRelease,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/admin/releases/{identifier}/reject
     */
    public function reject(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.approve') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.approve'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $rejectedRelease = $this->releaseService->transitionReleaseStatus($release, 'CANCELLED', $user);

        return response()->json([
            'success' => true,
            'message' => 'Release rejected and cancelled.',
            'data' => $rejectedRelease,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}/impact
     */
    public function impact(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $impact = $this->impactService->analyzeImpact($release->changes->toArray());

        return response()->json([
            'success' => true,
            'data' => $impact,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}/readiness
     */
    public function readiness(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $readiness = $this->readinessService->evaluateReadiness();

        return response()->json([
            'success' => true,
            'data' => $readiness,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}/verification
     */
    public function verification(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $verification = $this->verificationService->verifyRelease($release);

        return response()->json([
            'success' => true,
            'data' => $verification,
        ]);
    }

    /**
     * POST /api/v1/admin/releases/{identifier}/verify
     */
    public function runVerification(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.verify') && !$user->hasPermission('platform.releases.manage') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.verify'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $verification = $this->verificationService->verifyRelease($release);

        $release->update([
            'post_release_verification_status' => $verification['verification_status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post-release verification completed.',
            'data' => $verification,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}/timeline
     */
    public function timeline(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $timeline = $this->correlationService->reconstructReleaseTimeline($release);

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/{identifier}/rollback
     */
    public function rollback(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $release = $this->releaseService->getReleaseDetail($identifier);
        $rollback = $this->rollbackService->evaluateRollbackReadiness($release);

        return response()->json([
            'success' => true,
            'data' => $rollback,
        ]);
    }

    /**
     * GET /api/v1/admin/releases/compare
     */
    public function compare(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.releases.view'], 403);
        }

        $releaseIdA = (string) $request->query('release_a');
        $releaseIdB = (string) $request->query('release_b');

        if (empty($releaseIdA) || empty($releaseIdB)) {
            return response()->json(['message' => 'Both release_a and release_b query parameters are required for comparison.'], 422);
        }

        $releaseA = $this->releaseService->getReleaseDetail($releaseIdA);
        $releaseB = $this->releaseService->getReleaseDetail($releaseIdB);

        $comparison = $this->releaseService->compareReleases($releaseA, $releaseB);

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    /**
     * GET /api/v1/admin/changes
     */
    public function changesIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.changes.view') && !$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.changes.view'], 403);
        }

        $filters = $request->only(['release_id', 'category', 'impact_level', 'validation_status', 'search']);
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->changeService->listChanges($filters, $perPage, $user);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/changes/{identifier}
     */
    public function changesShow(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.changes.view') && !$user->hasPermission('platform.releases.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.changes.view'], 403);
        }

        $change = $this->changeService->getChangeDetail($identifier);

        return response()->json([
            'success' => true,
            'data' => $change,
        ]);
    }

    /**
     * POST /api/v1/admin/changes
     */
    public function changesStore(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.changes.manage') && !$user->hasPermission('platform.releases.manage') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.changes.manage'], 403);
        }

        $validated = $request->validate([
            'release_id' => 'nullable|exists:platform_releases,id',
            'change_identifier' => 'nullable|string|unique:platform_changes,change_identifier',
            'category' => 'required|string',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'environment' => 'nullable|string',
            'affected_applications' => 'nullable|array',
            'affected_services' => 'nullable|array',
            'impact_level' => 'nullable|string',
            'validation_status' => 'nullable|string',
        ]);

        $change = $this->changeService->registerChange($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Platform change registered successfully.',
            'data' => $change,
        ], 201);
    }
}
