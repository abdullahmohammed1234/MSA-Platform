<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Intelligence\CommunicationsIntelligenceService;
use App\Services\Intelligence\DonationIntelligenceService;
use App\Services\Intelligence\EmsIntelligenceService;
use App\Services\Intelligence\FeedbackIntelligenceService;
use App\Services\Intelligence\MlibmsIntelligenceService;
use App\Services\Intelligence\PlatformIntelligenceService;
use App\Services\Intelligence\StoreIntelligenceService;
use App\Services\Intelligence\VolunteerIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function __construct(
        private PlatformIntelligenceService $platformService,
        private EmsIntelligenceService $emsService,
        private DonationIntelligenceService $donationService,
        private StoreIntelligenceService $storeService,
        private MlibmsIntelligenceService $mlibmsService,
        private CommunicationsIntelligenceService $communicationsService,
        private VolunteerIntelligenceService $volunteerService,
        private FeedbackIntelligenceService $feedbackService
    ) {}

    private function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.view') && ! $user->hasPermission('view_analytics') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.view or view_analytics.'], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->platformService->getDashboardTelemetry($period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function ems(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->emsService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function donations(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->donationService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->storeService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function mlibms(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->mlibmsService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function communications(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->communicationsService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function volunteers(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->volunteerService->getAnalytics($period, $startDate, $endDate),
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeAdmin($request)) {
            return $authError;
        }

        $period = (string) $request->query('period', '30d');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'data' => $this->feedbackService->getAnalytics($period, $startDate, $endDate),
        ]);
    }
}
