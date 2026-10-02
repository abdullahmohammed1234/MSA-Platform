<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Volunteering\Services\VolunteerMatchingService;
use App\Volunteering\Services\VolunteerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerProfileController extends Controller
{
    public function __construct(
        protected VolunteerProfileService $profileService,
        protected VolunteerMatchingService $matchingService
    ) {}

    public function getProfile(Request $request): JsonResponse
    {
        $profile = $this->profileService->getProfileByUserId($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bio' => 'nullable|string|max:1000',
            'experience_level' => 'nullable|string|in:beginner,intermediate,advanced,expert',
            'years_experience' => 'nullable|numeric|min:0|max:50',
            'preferred_hours_per_week' => 'nullable|integer|min:1|max:80',
            'availability_days' => 'nullable|array',
            'availability_times' => 'nullable|array',
            'preferred_categories' => 'nullable|array',
            'privacy_level' => 'nullable|string|in:private,coordinator_only,public',
        ]);

        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $updated = $this->profileService->updateProfile($profile, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer profile updated successfully.',
            'data' => $updated,
        ]);
    }

    public function attachSkill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:volunteering_skills,id',
            'proficiency_level' => 'nullable|string|in:beginner,intermediate,advanced,expert',
        ]);

        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $updated = $this->profileService->attachSkill($profile, (int) $validated['skill_id'], $validated['proficiency_level'] ?? 'intermediate');

        return response()->json([
            'success' => true,
            'message' => 'Skill attached to profile.',
            'data' => $updated,
        ]);
    }

    public function detachSkill(Request $request, int $skillId): JsonResponse
    {
        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $updated = $this->profileService->detachSkill($profile, $skillId);

        return response()->json([
            'success' => true,
            'message' => 'Skill detached from profile.',
            'data' => $updated,
        ]);
    }

    public function attachInterest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interest_id' => 'required|exists:volunteering_interests,id',
        ]);

        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $updated = $this->profileService->attachInterest($profile, (int) $validated['interest_id']);

        return response()->json([
            'success' => true,
            'message' => 'Interest attached to profile.',
            'data' => $updated,
        ]);
    }

    public function detachInterest(Request $request, int $interestId): JsonResponse
    {
        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $updated = $this->profileService->detachInterest($profile, $interestId);

        return response()->json([
            'success' => true,
            'message' => 'Interest detached from profile.',
            'data' => $updated,
        ]);
    }

    public function addExperience(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization' => 'required|string|max:255',
            'role_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_current' => 'nullable|boolean',
        ]);

        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $experience = $this->profileService->addExperience($profile, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Volunteering experience added.',
            'data' => $experience,
        ], 201);
    }

    public function deleteExperience(Request $request, int $experienceId): JsonResponse
    {
        $profile = $this->profileService->getProfileByUserId($request->user()->id);
        $this->profileService->deleteExperience($profile, $experienceId);

        return response()->json([
            'success' => true,
            'message' => 'Volunteering experience removed.',
        ]);
    }

    public function getRecommendations(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $recommendations = $this->matchingService->getRecommendationsForVolunteer($request->user()->id, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    public function getInvitations(Request $request): JsonResponse
    {
        $invitations = \App\Volunteering\Models\VolunteerInvitation::with(['opportunity:id,title,slug,start_at,location', 'team:id,name', 'shift:id,name,start_at,end_at', 'inviter:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    public function acceptInvitation(Request $request, string $uuid): JsonResponse
    {
        $result = $this->profileService->acceptInvitation($uuid, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer invitation accepted and signup confirmed.',
            'data' => $result,
        ]);
    }

    public function declineInvitation(Request $request, string $uuid): JsonResponse
    {
        $invitation = $this->profileService->declineInvitation($uuid, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer invitation declined.',
            'data' => $invitation,
        ]);
    }

    public function listActiveSkills(): JsonResponse
    {
        $skills = $this->profileService->listSkills()->where('is_active', true)->values();

        return response()->json([
            'success' => true,
            'data' => $skills,
        ]);
    }

    public function listActiveInterests(): JsonResponse
    {
        $interests = $this->profileService->listInterests()->where('is_active', true)->values();

        return response()->json([
            'success' => true,
            'data' => $interests,
        ]);
    }
}
