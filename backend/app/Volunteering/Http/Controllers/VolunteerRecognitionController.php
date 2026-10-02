<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Volunteering\Services\VolunteerAchievementService;
use App\Volunteering\Services\VolunteerEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerRecognitionController extends Controller
{
    public function __construct(
        private readonly VolunteerAchievementService $achievementService,
        private readonly VolunteerEngagementService $engagementService
    ) {
    }

    /**
     * Get currently authenticated volunteer's recognition, achievements, and milestone progress.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Evaluate any pending automatic achievements first
        $newlyAwarded = $this->achievementService->evaluateUserAchievements($user);

        $recognition = $this->achievementService->getUserRecognitionSummary($user);
        $engagement = $this->engagementService->getVolunteerEngagementMetrics($user);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'summary' => [
                'earned_count' => $recognition['earned_count'],
                'total_points' => $recognition['total_points'],
                'total_service_hours' => $engagement['total_service_hours'],
                'completed_count' => $engagement['completed_count'],
                'retention_status' => $engagement['retention_status'],
            ],
            'next_milestone' => $engagement['next_milestone'],
            'newly_awarded' => $newlyAwarded,
            'achievements' => $recognition['achievements'],
        ]);
    }

    /**
     * Trigger explicit evaluation of automatic achievements for current user.
     */
    public function evaluate(Request $request): JsonResponse
    {
        $user = $request->user();

        $newlyAwarded = $this->achievementService->evaluateUserAchievements($user);
        $recognition = $this->achievementService->getUserRecognitionSummary($user);

        return response()->json([
            'success' => true,
            'newly_awarded_count' => $newlyAwarded->count(),
            'newly_awarded' => $newlyAwarded,
            'total_earned_count' => $recognition['earned_count'],
            'total_points' => $recognition['total_points'],
        ]);
    }
}
