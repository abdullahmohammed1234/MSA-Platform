<?php

namespace Tests\Feature\Vms;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Vms2HardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_guest_cannot_cancel_authenticated_user_signup(): void
    {
        $user = User::factory()->create(['email' => 'user@sfu.ca']);
        $opportunity = Opportunity::create([
            'title' => 'Security Opportunity',
            'slug' => 'sec-opp-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'signed_up',
        ]);

        // Unauthenticated attempt to cancel user's signup must be 403 Forbidden
        $response = $this->postJson("/api/v1/volunteering/signups/{$signup->uuid}/cancel");
        $response->assertStatus(403);

        $this->assertEquals('signed_up', $signup->fresh()->status);
    }

    /** @test */
    public function authenticated_user_can_cancel_own_signup(): void
    {
        $user = User::factory()->create(['email' => 'myaccount@sfu.ca']);
        $opportunity = Opportunity::create([
            'title' => 'User Cancel Test',
            'slug' => 'user-cancel-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'name' => 'My Account',
            'email' => 'myaccount@sfu.ca',
            'status' => 'signed_up',
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->postJson("/api/v1/volunteering/signups/{$signup->uuid}/cancel");
        $response->assertStatus(200);

        $this->assertEquals('cancelled', $signup->fresh()->status);
    }

    /** @test */
    public function guest_can_cancel_guest_signup_with_matching_email_verification(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Guest Cancel Test',
            'slug' => 'guest-cancel-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => null,
            'name' => 'Guest Volunteer',
            'email' => 'guest@example.com',
            'status' => 'signed_up',
        ]);

        // Attempt without email fails
        $res1 = $this->postJson("/api/v1/volunteering/signups/{$signup->uuid}/cancel");
        $res1->assertStatus(403);

        // Attempt with matching email succeeds
        $res2 = $this->postJson("/api/v1/volunteering/signups/{$signup->uuid}/cancel", [
            'email' => 'guest@example.com',
        ]);
        $res2->assertStatus(200);

        $this->assertEquals('cancelled', $signup->fresh()->status);
    }

    /** @test */
    public function team_capacity_enforced_when_team_id_omitted_from_payload(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Team Capacity Test',
            'slug' => 'team-cap-' . uniqid(),
            'status' => 'open',
        ]);

        $team = Team::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Small Team',
            'capacity' => 1,
            'status' => 'open',
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'team_id' => $team->id,
            'name' => 'Shift inside Small Team',
            'capacity' => 10,
            'status' => 'open',
        ]);

        // First signup takes team capacity (1/1)
        Signup::create([
            'opportunity_id' => $opportunity->id,
            'team_id' => $team->id,
            'shift_id' => $shift->id,
            'name' => 'First Member',
            'email' => 'first@example.com',
            'status' => 'signed_up',
        ]);

        // Second signup passes shift_id but omits team_id -> must fail because Team is full (1/1)
        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Second Member',
            'email' => 'second@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shift_id']);
    }

    /** @test */
    public function mismatched_team_and_shift_rejected(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Mismatched Boundary Event',
            'slug' => 'mismatched-' . uniqid(),
            'status' => 'open',
        ]);

        $teamA = Team::create(['opportunity_id' => $opportunity->id, 'name' => 'Team A', 'status' => 'open']);
        $teamB = Team::create(['opportunity_id' => $opportunity->id, 'name' => 'Team B', 'status' => 'open']);

        $shiftB = Shift::create([
            'opportunity_id' => $opportunity->id,
            'team_id' => $teamB->id,
            'name' => 'Shift B',
            'status' => 'open',
        ]);

        // Pass Team A with Shift B -> Should be rejected
        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'team_id' => $teamA->id,
            'shift_id' => $shiftB->id,
            'name' => 'Sneaky Volunteer',
            'email' => 'sneaky@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shift_id']);
    }

    /** @test */
    public function signup_blocked_for_ended_shift_or_opportunity(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Ended Event',
            'slug' => 'ended-event-' . uniqid(),
            'status' => 'open',
            'end_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Late Volunteer',
            'email' => 'late@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opportunity_id']);
    }
}
