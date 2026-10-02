<?php

namespace Tests\Feature\Vms;

use App\Models\ApplicationAccess;
use App\Models\User;
use App\Volunteering\Models\Achievement;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\UserAchievement;
use App\Volunteering\Models\VolunteerProfile;
use App\Volunteering\Services\VolunteerAchievementService;
use App\Volunteering\Services\VolunteerEngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class Vms8EngagementRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Event::fake();

        // Seed initial VMS-8 achievements
        $this->seed(\Database\Seeders\Vms8AchievementSeeder::class);
    }

    protected function createAuthorizedAdmin(): User
    {
        $admin = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'admin_vms8_' . Str::random(6) . '@sfu.ca',
        ]);

        ApplicationAccess::create([
            'user_id' => $admin->id,
            'application' => 'volunteering',
            'granted_by' => $admin->id,
        ]);

        return $admin;
    }

    protected function createVms8Volunteer(): User
    {
        return User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'volunteer_vms8_' . Str::random(6) . '@sfu.ca',
        ]);
    }

    public function test_authenticated_user_can_retrieve_recognition_and_milestones(): void
    {
        $user = $this->createVms8Volunteer();

        // Create profile
        VolunteerProfile::create([
            'user_id' => $user->id,
            'profile_completion_percentage' => 85,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/volunteering/recognition');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.retention_status', 'new');

        // Verify profile completed achievement auto-awarded
        $this->assertDatabaseHas('volunteering_user_achievements', [
            'user_id' => $user->id,
        ]);
    }

    public function test_automatic_achievement_evaluation_is_deterministic_and_idempotent(): void
    {
        $admin = $this->createAuthorizedAdmin();
        $user = $this->createVms8Volunteer();
        $opportunity = Opportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Community Iftar Setup',
            'slug' => 'community-iftar-setup-' . Str::random(5),
            'status' => 'open',
            'created_by' => $admin->id,
        ]);
        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Shift 1',
            'start_at' => now()->subHours(4),
            'end_at' => now(),
            'capacity' => 10,
            'status' => 'open',
        ]);

        // Create 1 completed signup (4 hours)
        Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'completed',
            'attendance_status' => 'present',
        ]);

        $achievementService = app(VolunteerAchievementService::class);

        // First evaluation
        $newlyAwarded1 = $achievementService->evaluateUserAchievements($user);
        $this->assertTrue($newlyAwarded1->contains(fn ($ua) => $ua->achievement->slug === 'first-contribution'));

        // Duplicate evaluation should return 0 new awards
        $newlyAwarded2 = $achievementService->evaluateUserAchievements($user);
        $this->assertCount(0, $newlyAwarded2);

        // Ensure database count for first-contribution is exactly 1
        $count = UserAchievement::query()
            ->where('user_id', $user->id)
            ->whereHas('achievement', fn ($q) => $q->where('slug', 'first-contribution'))
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_vms6_authoritative_service_hours_are_consumed_for_milestones(): void
    {
        $admin = $this->createAuthorizedAdmin();
        $user = $this->createVms8Volunteer();
        $opportunity = Opportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Food Drive Event',
            'slug' => 'food-drive-' . Str::random(5),
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        // Shift 1: 6 hours (completed) -> counted
        $shift1 = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Morning Shift',
            'start_at' => now()->subHours(6),
            'end_at' => now(),
            'capacity' => 10,
            'status' => 'open',
        ]);
        Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift1->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'completed',
            'attendance_status' => 'present',
        ]);

        // Shift 2: 5 hours (cancelled) -> excluded
        $shift2 = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Afternoon Shift',
            'start_at' => now()->subHours(5),
            'end_at' => now(),
            'capacity' => 10,
            'status' => 'open',
        ]);
        Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift2->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'cancelled',
            'attendance_status' => 'absent',
        ]);

        $achievementService = app(VolunteerAchievementService::class);
        $achievementService->evaluateUserAchievements($user);

        // Should receive 5 Verified Hours milestone (since 6.0 >= 5.0), but NOT 10 hours
        $this->assertDatabaseHas('volunteering_user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => Achievement::where('slug', 'hours-5')->first()->id,
        ]);

        $this->assertDatabaseMissing('volunteering_user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => Achievement::where('slug', 'hours-10')->first()->id,
        ]);
    }

    public function test_admin_can_manage_achievement_taxonomy_and_award_manually(): void
    {
        $admin = $this->createAuthorizedAdmin();
        $volunteer = $this->createVms8Volunteer();

        // 1. Create custom achievement definition
        $createRes = $this->actingAs($admin)
            ->postJson('/api/v1/admin/volunteering/achievements', [
                'name' => 'Special Event Hero',
                'slug' => 'special-event-hero',
                'description' => 'Outstanding contribution during annual MSA gala.',
                'category' => 'leadership',
                'icon' => 'star',
                'rule_type' => 'completed_opportunities',
                'criteria_config' => ['threshold' => 1],
                'points' => 50,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.slug', 'special-event-hero');

        $achievementId = $createRes->json('data.id');

        // 2. Manually award achievement to volunteer
        $awardRes = $this->actingAs($admin)
            ->postJson("/api/v1/admin/volunteering/volunteers/{$volunteer->id}/award", [
                'achievement_id' => $achievementId,
                'reason' => 'Recognized by event coordinator for exemplary leadership.',
            ]);

        $awardRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Attempting duplicate manual award should return 409 Conflict
        $dupRes = $this->actingAs($admin)
            ->postJson("/api/v1/admin/volunteering/volunteers/{$volunteer->id}/award", [
                'achievement_id' => $achievementId,
            ]);

        $dupRes->assertStatus(409);
    }

    public function test_retention_classification_and_reengagement_candidate_discovery(): void
    {
        $admin = $this->createAuthorizedAdmin();

        // Inactive volunteer profile
        $inactiveUser = $this->createVms8Volunteer();
        VolunteerProfile::create([
            'user_id' => $inactiveUser->id,
            'experience_level' => 'intermediate',
        ]);

        $opportunity = Opportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Upcoming Orientation',
            'slug' => 'upcoming-orientation-' . Str::random(5),
            'status' => 'published',
            'start_at' => now()->addDays(7),
            'created_by' => $admin->id,
        ]);

        $pastShift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Past Shift',
            'start_at' => now()->subDays(80)->subHours(3),
            'end_at' => now()->subDays(80),
            'capacity' => 10,
            'status' => 'open',
        ]);

        Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'shift_id' => $pastShift->id,
            'user_id' => $inactiveUser->id,
            'name' => $inactiveUser->name,
            'email' => $inactiveUser->email,
            'status' => 'completed',
            'created_at' => now()->subDays(80),
        ]);

        $engagementService = app(VolunteerEngagementService::class);
        $metrics = $engagementService->getVolunteerEngagementMetrics($inactiveUser);

        $this->assertEquals('inactive', $metrics['retention_status']);

        // Check retention admin overview API
        $retentionRes = $this->actingAs($admin)
            ->getJson('/api/v1/admin/volunteering/retention');

        $retentionRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check re-engagement candidates API
        $reengageRes = $this->actingAs($admin)
            ->getJson('/api/v1/admin/volunteering/reengagement');

        $reengageRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Log outreach action
        $outreachRes = $this->actingAs($admin)
            ->postJson('/api/v1/admin/volunteering/reengagement/outreach', [
                'user_id' => $inactiveUser->id,
                'opportunity_id' => $opportunity->id,
                'channel' => 'email',
            ]);

        $outreachRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('volunteering_reengagement_logs', [
            'user_id' => $inactiveUser->id,
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function test_unauthenticated_and_unauthorized_users_are_forbidden(): void
    {
        $volunteer = $this->createVms8Volunteer();

        // 1. Unauthenticated request to recognition returns 401
        $this->getJson('/api/v1/volunteering/recognition')
            ->assertStatus(401);

        // 2. Volunteer without admin app access cannot access admin recognition APIs
        $this->actingAs($volunteer)
            ->getJson('/api/v1/admin/volunteering/achievements')
            ->assertStatus(403);

        $this->actingAs($volunteer)
            ->getJson('/api/v1/admin/volunteering/retention')
            ->assertStatus(403);
    }
}
