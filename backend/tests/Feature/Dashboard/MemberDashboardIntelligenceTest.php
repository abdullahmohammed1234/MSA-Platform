<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDashboardIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_unauthorized_for_dashboard_endpoint(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_personalized_dashboard_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $profile = VolunteerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['profile_completion_percentage' => 60]
        );

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['uuid', 'name', 'email', 'community_status', 'email_verified'],
                'action_items',
                'recommended_opportunities',
                'volunteer' => [
                    'status',
                    'completion_percentage',
                    'upcoming_shifts',
                    'completed_shifts',
                    'commitments',
                    'pending_applications',
                    'attendance_summary' => ['attended_count', 'absent_count', 'attendance_note'],
                ],
                'applications',
            ]);

        $actionIds = collect($response->json('action_items'))->pluck('id')->toArray();
        $this->assertContains('verify_email', $actionIds);
        $this->assertContains('complete_volunteer_profile', $actionIds);
    }

    public function test_user_isolation_user_a_cannot_see_user_b_signups(): void
    {
        /** @var User $userA */
        $userA = User::factory()->create();
        /** @var User $userB */
        $userB = User::factory()->create();

        $opp = Opportunity::create([
            'title' => 'Community Iftar Support',
            'slug' => 'community-iftar-support',
            'description' => 'Help serve Iftar to community members.',
            'status' => 'published',
            'start_at' => now()->addDays(2),
        ]);

        Signup::create([
            'user_id' => $userB->id,
            'opportunity_id' => $opp->id,
            'name' => $userB->name,
            'email' => $userB->email,
            'status' => 'approved',
            'attendance_status' => 'attended',
        ]);

        // Request dashboard for User A
        $responseA = $this->actingAs($userA)->getJson('/api/v1/dashboard');

        $responseA->assertStatus(200);
        $commitmentsA = $responseA->json('volunteer.commitments');
        $this->assertEmpty($commitmentsA);
        $this->assertEquals(0, $responseA->json('volunteer.completed_shifts'));

        // Request dashboard for User B
        $responseB = $this->actingAs($userB)->getJson('/api/v1/dashboard');
        $responseB->assertStatus(200);
        $this->assertEquals(1, $responseB->json('volunteer.completed_shifts'));
        $this->assertEquals(1, $responseB->json('volunteer.attendance_summary.attended_count'));
    }

    public function test_applied_opportunities_are_excluded_from_new_recommendations(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $oppApplied = Opportunity::create([
            'title' => 'Already Applied Opp',
            'slug' => 'already-applied-opp',
            'description' => 'User signed up for this.',
            'status' => 'published',
            'start_at' => now()->addDays(3),
        ]);

        $oppNew = Opportunity::create([
            'title' => 'Fresh Opportunity',
            'slug' => 'fresh-opportunity',
            'description' => 'User has not signed up.',
            'status' => 'published',
            'start_at' => now()->addDays(5),
        ]);

        Signup::create([
            'user_id' => $user->id,
            'opportunity_id' => $oppApplied->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $recommended = $response->json('recommended_opportunities');

        $recSlugs = collect($recommended)->pluck('slug')->toArray();
        $this->assertContains('fresh-opportunity', $recSlugs);
        $this->assertNotContains('already-applied-opp', $recSlugs);
    }
}

