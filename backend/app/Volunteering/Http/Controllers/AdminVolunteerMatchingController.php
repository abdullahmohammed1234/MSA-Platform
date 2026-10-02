<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Volunteering\Models\Interest;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Skill;
use App\Volunteering\Models\VolunteerProfile;
use App\Volunteering\Services\VolunteerMatchingService;
use App\Volunteering\Services\VolunteerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVolunteerMatchingController extends Controller
{
    public function __construct(
        protected VolunteerProfileService $profileService,
        protected VolunteerMatchingService $matchingService
    ) {}

    // Taxonomy Skills Management
    public function listSkills(): JsonResponse
    {
        $skills = $this->profileService->listSkills();
        return response()->json(['success' => true, 'data' => $skills]);
    }

    public function storeSkill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:volunteering_skills,slug',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $skill = $this->profileService->createSkill($validated);
        return response()->json(['success' => true, 'message' => 'Skill created successfully.', 'data' => $skill], 201);
    }

    public function updateSkill(Request $request, int $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:volunteering_skills,slug,' . $id,
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $updated = $this->profileService->updateSkill($skill, $validated);
        return response()->json(['success' => true, 'message' => 'Skill updated successfully.', 'data' => $updated]);
    }

    public function destroySkill(int $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);
        $skill->delete();
        return response()->json(['success' => true, 'message' => 'Skill deleted successfully.']);
    }

    // Taxonomy Interests Management
    public function listInterests(): JsonResponse
    {
        $interests = $this->profileService->listInterests();
        return response()->json(['success' => true, 'data' => $interests]);
    }

    public function storeInterest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:volunteering_interests,slug',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $interest = $this->profileService->createInterest($validated);
        return response()->json(['success' => true, 'message' => 'Interest created successfully.', 'data' => $interest], 201);
    }

    public function updateInterest(Request $request, int $id): JsonResponse
    {
        $interest = Interest::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:volunteering_interests,slug,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $updated = $this->profileService->updateInterest($interest, $validated);
        return response()->json(['success' => true, 'message' => 'Interest updated successfully.', 'data' => $updated]);
    }

    public function destroyInterest(int $id): JsonResponse
    {
        $interest = Interest::findOrFail($id);
        $interest->delete();
        return response()->json(['success' => true, 'message' => 'Interest deleted successfully.']);
    }

    // Opportunity Requirement Management
    public function updateOpportunityRequirements(Request $request, int $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $validated = $request->validate([
            'skills' => 'nullable|array',
            'skills.*.skill_id' => 'required|exists:volunteering_skills,id',
            'skills.*.is_required' => 'nullable|boolean',
            'skills.*.min_proficiency' => 'nullable|string|in:beginner,intermediate,advanced,expert',
            'interests' => 'nullable|array',
            'interests.*' => 'required|exists:volunteering_interests,id',
        ]);

        $updated = $this->profileService->updateOpportunityRequirements(
            $opportunity,
            $validated['skills'] ?? [],
            $validated['interests'] ?? []
        );

        return response()->json(['success' => true, 'message' => 'Opportunity requirements updated.', 'data' => $updated]);
    }

    // Candidate Matching & Invitations
    public function getOpportunityMatches(Request $request, int $id): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);
        $matches = $this->matchingService->findMatchesForOpportunity($id, $limit);

        return response()->json(['success' => true, 'data' => $matches]);
    }

    public function inviteVolunteer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'team_id' => 'nullable|exists:volunteering_teams,id',
            'shift_id' => 'nullable|exists:volunteering_shifts,id',
            'message' => 'nullable|string|max:500',
        ]);

        $invitation = $this->profileService->inviteVolunteer(
            $id,
            (int) $validated['user_id'],
            $request->user()->id,
            $validated
        );

        return response()->json(['success' => true, 'message' => 'Volunteer invitation sent.', 'data' => $invitation], 201);
    }

    // Coordinator Volunteer Profiles Directory
    public function listProfiles(Request $request): JsonResponse
    {
        $query = VolunteerProfile::with(['user:id,name,email', 'skills', 'interests'])
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
            });

        if ($request->query('experience_level')) {
            $query->where('experience_level', $request->query('experience_level'));
        }

        if ($request->query('skill_id')) {
            $query->whereHas('skills', function ($q) use ($request) {
                $q->where('volunteering_skills.id', $request->query('skill_id'));
            });
        }

        if ($request->query('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('email', 'like', $search);
            });
        }

        $profiles = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $profiles->items(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    public function showProfile(int $id): JsonResponse
    {
        $profile = VolunteerProfile::with(['user:id,name,email', 'skills', 'interests', 'experiences'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $profile]);
    }
}
