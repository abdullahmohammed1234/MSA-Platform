<?php

namespace Tests\Feature\Vms;

use App\Models\Role;
use App\Models\User;
use App\Volunteering\Models\Interest;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Skill;
use App\Volunteering\Models\VolunteerInvitation;
use App\Volunteering\Models\VolunteerProfile;
use App\Volunteering\Services\EligibilityEvaluator;
use App\Volunteering\Services\VolunteerMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Vms7ProfilesAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $volunteerUser;
    protected Skill $skillLogistics;
    protected Skill $skillMedia;
    protected Interest $interestOutreach;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Admin Role & User
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $this->adminUser = User::factory()->create([
            'email' => 'admin.vms7@sfu.ca',
            'email_verified_at' => now(),
        ]);
        $this->adminUser->assignRole($adminRole);
        \App\Models\ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'volunteering',
            'granted_by' => $this->adminUser->id,
        ]);

        // 2. Volunteer User
        $this->volunteerUser = User::factory()->create([
            'name' => 'Sara Volunteer',
            'email' => 'sara.volunteer@sfu.ca',
            'email_verified_at' => now(),
        ]);

        // 3. Taxonomies
        $this->skillLogistics = Skill::create([
            'name' => 'Logistics & Setup',
            'slug' => 'logistics-setup',
            'category' => 'Operations',
            'is_active' => true,
        ]);

        $this->skillMedia = Skill::create([
            'name' => 'Media & Photography',
            'slug' => 'media-photography',
            'category' => 'Creative',
            'is_active' => true,
        ]);

        $this->interestOutreach = Interest::create([
            'name' => 'Community Outreach',
            'slug' => 'community-outreach',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_manage_volunteer_profile_skills_interests_and_experiences(): void
    {
        // 1. Fetch initial profile (auto-created via service)
        $response = $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/volunteering/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $this->volunteerUser->id);

        // 2. Update basic profile
        $updateRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->putJson('/api/v1/volunteering/profile', [
                'bio' => 'Passionate student volunteer.',
                'experience_level' => 'intermediate',
                'years_experience' => 2.5,
                'preferred_hours_per_week' => 6,
                'availability_days' => ['saturday', 'sunday'],
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.experience_level', 'intermediate')
            ->assertJsonPath('data.years_experience', 2.5);

        // 3. Attach skill
        $skillRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->postJson('/api/v1/volunteering/profile/skills', [
                'skill_id' => $this->skillLogistics->id,
                'proficiency_level' => 'advanced',
            ]);

        $skillRes->assertStatus(200);
        $this->assertDatabaseHas('volunteering_profile_skills', [
            'skill_id' => $this->skillLogistics->id,
            'proficiency_level' => 'advanced',
        ]);

        // 4. Attach interest
        $interestRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->postJson('/api/v1/volunteering/profile/interests', [
                'interest_id' => $this->interestOutreach->id,
            ]);

        $interestRes->assertStatus(200);
        $this->assertDatabaseHas('volunteering_profile_interests', [
            'interest_id' => $this->interestOutreach->id,
        ]);

        // 5. Add external experience
        $expRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->postJson('/api/v1/volunteering/profile/experiences', [
                'organization' => 'SFU Food Bank',
                'role_title' => 'Shift Lead',
                'description' => 'Managed volunteer distribution desk.',
            ]);

        $expRes->assertStatus(201)
            ->assertJsonPath('data.organization', 'SFU Food Bank');

        // Verify completion percentage updated
        $finalProfileRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/volunteering/profile');

        $completion = $finalProfileRes->json('data.profile_completion_percentage');
        $this->assertGreaterThan(50, $completion);
    }

    /** @test */
    public function eligibility_evaluator_correctly_blocks_missing_required_skills(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Annual Gala Logistics',
            'description' => 'Stage setup and gear management.',
            'status' => 'open',
            'created_by' => $this->adminUser->id,
        ]);

        // Require Logistics skill
        $opportunity->skills()->attach($this->skillLogistics->id, [
            'is_required' => true,
            'min_proficiency' => 'intermediate',
        ]);

        $profile = VolunteerProfile::create(['user_id' => $this->volunteerUser->id, 'experience_level' => 'beginner']);

        $evaluator = new EligibilityEvaluator();
        $result = $evaluator->evaluate($profile, $opportunity);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Missing required skill', $result['reasons'][0]);

        // Now attach skill and re-evaluate
        $profile->skills()->attach($this->skillLogistics->id, ['proficiency_level' => 'intermediate']);
        $profile->unsetRelation('skills');

        $resultAfter = $evaluator->evaluate($profile, $opportunity);
        $this->assertTrue($resultAfter['eligible']);
    }

    /** @test */
    public function matching_engine_calculates_deterministic_bounded_match_scores_and_explanations(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Media & Outreach Booth',
            'description' => 'Help capture photos and engage visitors.',
            'status' => 'open',
            'start_at' => now()->next('Saturday'),
            'created_by' => $this->adminUser->id,
        ]);

        $opportunity->skills()->attach($this->skillMedia->id, ['is_required' => false]);
        $opportunity->interests()->attach($this->interestOutreach->id);

        // Volunteer A: Has media skill and outreach interest
        $profileA = VolunteerProfile::create([
            'user_id' => $this->volunteerUser->id,
            'experience_level' => 'advanced',
            'availability_days' => ['saturday'],
        ]);
        $profileA->skills()->attach($this->skillMedia->id, ['proficiency_level' => 'advanced']);
        $profileA->interests()->attach($this->interestOutreach->id);

        // Volunteer B: Other user without matching skills
        $userB = User::factory()->create(['email' => 'userb.vms7@sfu.ca']);
        $profileB = VolunteerProfile::create([
            'user_id' => $userB->id,
            'experience_level' => 'beginner',
        ]);

        $matchingService = app(VolunteerMatchingService::class);
        $matchA = $matchingService->calculateMatch($profileA, $opportunity);
        $matchB = $matchingService->calculateMatch($profileB, $opportunity);

        $this->assertTrue($matchA['eligible']);
        $this->assertGreaterThan($matchB['score'], $matchA['score']);
        $this->assertNotEmpty($matchA['reasons']);

        // Admin candidate search
        $adminRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}/matches");

        $adminRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $firstMatch = $adminRes->json('data.0');
        $this->assertEquals($this->volunteerUser->id, $firstMatch['user_id']);

        // Volunteer recommendation search
        $recRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/volunteering/recommendations');

        $recRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals($opportunity->id, $recRes->json('data.0.opportunity.id'));
    }

    /** @test */
    public function admin_can_invite_volunteer_candidate_and_volunteer_can_accept_to_signup(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Special Event Coordinator',
            'status' => 'open',
            'capacity' => 10,
            'created_by' => $this->adminUser->id,
        ]);

        // 1. Admin sends invitation
        $inviteRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}/invite", [
                'user_id' => $this->volunteerUser->id,
                'message' => 'We would love your support for this event!',
            ]);

        $inviteRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $invitationUuid = $inviteRes->json('data.uuid');
        $this->assertDatabaseHas('volunteering_invitations', [
            'uuid' => $invitationUuid,
            'user_id' => $this->volunteerUser->id,
            'status' => 'pending',
        ]);

        // 2. Volunteer checks invitations
        $invListRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/volunteering/invitations');

        $invListRes->assertStatus(200)
            ->assertJsonPath('data.0.uuid', $invitationUuid);

        // 3. Volunteer accepts invitation
        $acceptRes = $this->actingAs($this->volunteerUser, 'sanctum')
            ->postJson("/api/v1/volunteering/invitations/{$invitationUuid}/accept");

        $acceptRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('volunteering_invitations', [
            'uuid' => $invitationUuid,
            'status' => 'accepted',
        ]);

        // Verify standard signup was created
        $this->assertDatabaseHas('volunteering_signups', [
            'opportunity_id' => $opportunity->id,
            'user_id' => $this->volunteerUser->id,
            'status' => 'signed_up',
        ]);
    }

    /** @test */
    public function unauthenticated_or_unauthorized_users_are_forbidden_from_admin_matching_routes(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/volunteering/profile')->assertStatus(401);
        $this->getJson('/api/v1/admin/volunteering/skills')->assertStatus(401);

        // Regular volunteer accessing admin matching endpoint
        $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/admin/volunteering/skills')
            ->assertStatus(403);
    }
}
