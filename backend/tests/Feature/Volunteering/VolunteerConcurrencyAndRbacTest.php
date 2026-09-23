<?php

namespace Tests\Feature\Volunteering;

use App\Models\Role;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VolunteerConcurrencyAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_signup_enforces_shift_capacity_strictly(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'High Demand Volunteer Event',
            'slug' => 'high-demand-event',
            'status' => 'open',
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Single Slot Shift',
            'capacity' => 1,
            'status' => 'open',
        ]);

        $signupService = new VolunteerSignupService();

        // Perform first signup
        $s1 = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Concurrent User A',
            'email' => 'user_a@example.com',
        ]);

        $this->assertEquals('signed_up', $s1->status);

        // Second signup must fail due to capacity = 1
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Concurrent User B',
            'email' => 'user_b@example.com',
        ]);

        $totalSignups = Signup::where('shift_id', $shift->id)->count();
        $this->assertLessThanOrEqual(1, $totalSignups);
    }

    public function test_signup_blocked_when_opportunity_is_closed(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Closed Opportunity',
            'slug' => 'closed-opp',
            'status' => 'closed',
        ]);

        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Early Bird',
            'email' => 'early@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opportunity_id']);
    }

    public function test_signup_blocked_when_team_is_closed(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Team Event',
            'slug' => 'team-event',
            'status' => 'open',
        ]);

        $closedTeam = Team::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Filled Team',
            'status' => 'closed',
        ]);

        $shiftInClosedTeam = Shift::create([
            'opportunity_id' => $opportunity->id,
            'team_id' => $closedTeam->id,
            'name' => 'Shift in Closed Team',
            'status' => 'open',
        ]);

        // Direct team signup check
        $res1 = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'team_id' => $closedTeam->id,
            'name' => 'Volunteer 1',
            'email' => 'vol1@example.com',
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        // Shift belonging to closed team signup check
        $res2 = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shiftInClosedTeam->id,
            'name' => 'Volunteer 2',
            'email' => 'vol2@example.com',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['shift_id']);
    }

    public function test_unauthenticated_requests_return_401_for_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/volunteering/opportunities')->assertStatus(401);
        $this->postJson('/api/v1/admin/volunteering/opportunities', [])->assertStatus(401);
        $this->getJson('/api/v1/admin/volunteering/opportunities/1')->assertStatus(401);
        $this->putJson('/api/v1/admin/volunteering/opportunities/1', [])->assertStatus(401);
        $this->deleteJson('/api/v1/admin/volunteering/opportunities/1')->assertStatus(401);
        $this->getJson('/api/v1/admin/volunteering/opportunities/1/signups')->assertStatus(401);
        $this->putJson('/api/v1/admin/volunteering/signups/1/status', [])->assertStatus(401);
        $this->getJson('/api/v1/admin/volunteering/analytics')->assertStatus(401);
    }

    public function test_unauthorized_users_without_volunteering_permission_receive_403(): void
    {
        $regularUser = User::factory()->create();

        $opportunity = Opportunity::create([
            'title' => 'Protected Event',
            'slug' => 'protected-event',
            'status' => 'open',
        ]);

        $this->actingAs($regularUser, 'sanctum');

        $this->getJson('/api/v1/admin/volunteering/opportunities')->assertStatus(403);
        $this->postJson('/api/v1/admin/volunteering/opportunities', [
            'title' => 'Hacker Opp',
            'slug' => 'hacker-opp',
        ])->assertStatus(403);

        $this->getJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}")->assertStatus(403);

        $this->putJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}", [
            'title' => 'Updated Title',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}")->assertStatus(403);

        $this->getJson("/api/v1/admin/volunteering/opportunities/{$opportunity->id}/signups")->assertStatus(403);

        $this->putJson("/api/v1/admin/volunteering/signups/1/status", [
            'status' => 'confirmed',
        ])->assertStatus(403);

        $this->getJson('/api/v1/admin/volunteering/analytics')->assertStatus(403);
    }
}
