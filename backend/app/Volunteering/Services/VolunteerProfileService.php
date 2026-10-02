<?php

namespace App\Volunteering\Services;

use App\Models\User;
use App\Volunteering\Models\Interest;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Skill;
use App\Volunteering\Models\VolunteerExperience;
use App\Volunteering\Models\VolunteerInvitation;
use App\Volunteering\Models\VolunteerProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class VolunteerProfileService
{
    public function __construct(
        protected VolunteerSignupService $signupService
    ) {}

    /**
     * Get or create a volunteer profile for a user.
     */
    public function getProfileByUserId(int $userId): VolunteerProfile
    {
        $profile = VolunteerProfile::with(['skills', 'interests', 'experiences', 'user:id,name,email'])
            ->firstOrCreate(
                ['user_id' => $userId],
                [
                    'experience_level' => 'beginner',
                    'privacy_level' => 'private',
                    'profile_completion_percentage' => 20,
                ]
            );

        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences', 'user:id,name,email']);
    }

    /**
     * Update profile details.
     */
    public function updateProfile(VolunteerProfile $profile, array $data): VolunteerProfile
    {
        $profile->update(array_filter([
            'bio' => $data['bio'] ?? $profile->bio,
            'experience_level' => $data['experience_level'] ?? $profile->experience_level,
            'years_experience' => isset($data['years_experience']) ? (float) $data['years_experience'] : $profile->years_experience,
            'preferred_hours_per_week' => isset($data['preferred_hours_per_week']) ? (int) $data['preferred_hours_per_week'] : $profile->preferred_hours_per_week,
            'availability_days' => $data['availability_days'] ?? $profile->availability_days,
            'availability_times' => $data['availability_times'] ?? $profile->availability_times,
            'preferred_categories' => $data['preferred_categories'] ?? $profile->preferred_categories,
            'privacy_level' => $data['privacy_level'] ?? $profile->privacy_level,
        ], fn ($val) => $val !== null));

        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences', 'user:id,name,email']);
    }

    /**
     * Attach a skill to a profile.
     */
    public function attachSkill(VolunteerProfile $profile, int $skillId, string $proficiencyLevel = 'intermediate'): VolunteerProfile
    {
        $skill = Skill::where('is_active', true)->findOrFail($skillId);

        $profile->skills()->syncWithoutDetaching([
            $skill->id => ['proficiency_level' => $proficiencyLevel]
        ]);

        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences']);
    }

    /**
     * Detach a skill from a profile.
     */
    public function detachSkill(VolunteerProfile $profile, int $skillId): VolunteerProfile
    {
        $profile->skills()->detach($skillId);
        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences']);
    }

    /**
     * Attach an interest to a profile.
     */
    public function attachInterest(VolunteerProfile $profile, int $interestId): VolunteerProfile
    {
        $interest = Interest::where('is_active', true)->findOrFail($interestId);

        $profile->interests()->syncWithoutDetaching([$interest->id]);
        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences']);
    }

    /**
     * Detach an interest from a profile.
     */
    public function detachInterest(VolunteerProfile $profile, int $interestId): VolunteerProfile
    {
        $profile->interests()->detach($interestId);
        $profile->calculateCompletionPercentage();
        return $profile->fresh(['skills', 'interests', 'experiences']);
    }

    /**
     * Add an external experience.
     */
    public function addExperience(VolunteerProfile $profile, array $data): VolunteerExperience
    {
        $experience = $profile->experiences()->create([
            'organization' => $data['organization'],
            'role_title' => $data['role_title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_current' => (bool) ($data['is_current'] ?? false),
        ]);

        $profile->calculateCompletionPercentage();
        return $experience;
    }

    /**
     * Delete an external experience.
     */
    public function deleteExperience(VolunteerProfile $profile, int $experienceId): void
    {
        $profile->experiences()->where('id', $experienceId)->delete();
        $profile->calculateCompletionPercentage();
    }

    /**
     * Manage Opportunity Requirements (Skills & Interests).
     */
    public function updateOpportunityRequirements(Opportunity $opportunity, array $skills, array $interests): Opportunity
    {
        // Format skills array: [ ['skill_id' => 1, 'is_required' => true, 'min_proficiency' => 'intermediate'] ]
        $syncSkills = [];
        foreach ($skills as $s) {
            $syncSkills[$s['skill_id']] = [
                'is_required' => (bool) ($s['is_required'] ?? false),
                'min_proficiency' => $s['min_proficiency'] ?? 'beginner',
            ];
        }
        $opportunity->skills()->sync($syncSkills);

        // Format interests array: [ 1, 2, 3 ]
        $opportunity->interests()->sync($interests);

        return $opportunity->fresh(['skills', 'interests']);
    }

    /**
     * Create and send a volunteer invitation.
     */
    public function inviteVolunteer(int $opportunityId, int $userId, int $invitedById, array $payload = []): VolunteerInvitation
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $user = User::findOrFail($userId);

        $invitation = VolunteerInvitation::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'team_id' => $payload['team_id'] ?? null,
            'shift_id' => $payload['shift_id'] ?? null,
            'user_id' => $user->id,
            'invited_by' => $invitedById,
            'status' => 'pending',
            'message' => $payload['message'] ?? null,
            'expires_at' => now()->addDays(7),
        ]);

        return $invitation->load(['opportunity', 'team', 'shift', 'user:id,name,email', 'inviter:id,name']);
    }

    /**
     * Accept a volunteer invitation (routes to standard signup logic).
     */
    public function acceptInvitation(string $uuid, int $userId): array
    {
        $invitation = VolunteerInvitation::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($invitation->status !== 'pending') {
            throw new \InvalidArgumentException("Invitation is no longer pending (current status: {$invitation->status}).");
        }

        $user = User::findOrFail($userId);

        // Perform standard signup preserving capacity & lifecycle validation
        $signupPayload = [
            'opportunity_id' => $invitation->opportunity_id,
            'team_id' => $invitation->team_id,
            'shift_id' => $invitation->shift_id,
            'name' => $user->name,
            'email' => $user->email,
            'notes' => 'Registered via volunteer invitation.',
        ];

        $signup = $this->signupService->registerSignup($signupPayload, $user->id);

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return [
            'invitation' => $invitation,
            'signup' => $signup,
        ];
    }

    /**
     * Decline a volunteer invitation.
     */
    public function declineInvitation(string $uuid, int $userId): VolunteerInvitation
    {
        $invitation = VolunteerInvitation::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->firstOrFail();

        $invitation->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);

        return $invitation;
    }

    /**
     * Admin Skills Taxonomy CRUD.
     */
    public function listSkills(): Collection
    {
        return Skill::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
    }

    public function createSkill(array $data): Skill
    {
        return Skill::create([
            'name' => $data['name'],
            'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function updateSkill(Skill $skill, array $data): Skill
    {
        $skill->update(array_filter([
            'name' => $data['name'] ?? $skill->name,
            'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : $skill->slug,
            'description' => $data['description'] ?? $skill->description,
            'category' => $data['category'] ?? $skill->category,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $skill->is_active,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $skill->sort_order,
        ], fn ($v) => $v !== null));

        return $skill;
    }

    /**
     * Admin Interests Taxonomy CRUD.
     */
    public function listInterests(): Collection
    {
        return Interest::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
    }

    public function createInterest(array $data): Interest
    {
        return Interest::create([
            'name' => $data['name'],
            'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function updateInterest(Interest $interest, array $data): Interest
    {
        $interest->update(array_filter([
            'name' => $data['name'] ?? $interest->name,
            'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : $interest->slug,
            'description' => $data['description'] ?? $interest->description,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $interest->is_active,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $interest->sort_order,
        ], fn ($v) => $v !== null));

        return $interest;
    }
}
