<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Volunteering\Models\Achievement;
use App\Volunteering\Services\VolunteerAchievementService;
use App\Volunteering\Services\VolunteerEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminVolunteerRecognitionController extends Controller
{
    public function __construct(
        private readonly VolunteerAchievementService $achievementService,
        private readonly VolunteerEngagementService $engagementService
    ) {
    }

    /**
     * List all achievement definitions in taxonomy.
     */
    public function listAchievements(): JsonResponse
    {
        $achievements = Achievement::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    /**
     * Create a new achievement definition in database.
     */
    public function storeAchievement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:volunteering_achievements,slug'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:50'],
            'rule_type' => ['required', 'string', Rule::in(['completed_opportunities', 'verified_service_hours', 'attendance_count', 'profile_completion'])],
            'criteria_config' => ['required', 'array'],
            'points' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $achievement = Achievement::create($validated);

        return response()->json([
            'success' => true,
            'data' => $achievement,
        ], 201);
    }

    /**
     * Update an achievement definition.
     */
    public function updateAchievement(Request $request, int $id): JsonResponse
    {
        $achievement = Achievement::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('volunteering_achievements', 'slug')->ignore($achievement->id)],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'rule_type' => ['sometimes', 'string', Rule::in(['completed_opportunities', 'verified_service_hours', 'attendance_count', 'profile_completion'])],
            'criteria_config' => ['sometimes', 'array'],
            'points' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $achievement->update($validated);

        return response()->json([
            'success' => true,
            'data' => $achievement,
        ]);
    }

    /**
     * Manually award an achievement to a volunteer.
     */
    public function awardVolunteer(Request $request, int $userId): JsonResponse
    {
        $targetUser = User::query()->findOrFail($userId);

        $validated = $request->validate([
            'achievement_id' => ['required', 'integer', 'exists:volunteering_achievements,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $adminUser = $request->user();

        $userAchievement = $this->achievementService->awardManualAchievement(
            user: $targetUser,
            achievementId: (int) $validated['achievement_id'],
            adminUser: $adminUser,
            reason: $validated['reason'] ?? null
        );

        if ($userAchievement === null) {
            return response()->json([
                'success' => false,
                'message' => 'Volunteer already has this achievement awarded.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Achievement awarded successfully.',
            'data' => $userAchievement->load('achievement'),
        ]);
    }

    /**
     * Get aggregate retention breakdown metrics for program management.
     */
    public function retentionOverview(): JsonResponse
    {
        $overview = $this->engagementService->getRetentionOverview();

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * Get re-engagement candidate suggestions.
     */
    public function reengagementCandidates(): JsonResponse
    {
        $candidates = $this->engagementService->getReengagementCandidates();

        return response()->json([
            'success' => true,
            'data' => $candidates,
        ]);
    }

    /**
     * Log re-engagement outreach action.
     */
    public function logOutreach(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:volunteering_opportunities,id'],
            'channel' => ['sometimes', 'string', 'max:50'],
        ]);

        $log = $this->engagementService->logReengagementOutreach(
            userId: (int) $validated['user_id'],
            opportunityId: isset($validated['opportunity_id']) ? (int) $validated['opportunity_id'] : null,
            channel: $validated['channel'] ?? 'email'
        );

        return response()->json([
            'success' => true,
            'message' => 'Re-engagement outreach logged successfully.',
            'data' => $log,
        ]);
    }
}
