<?php

namespace Tests\Feature\Vms;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Vms4VolunteerExperienceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function public_opportunity_browsing_returns_authoritative_status_and_counts(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Community Clean Up',
            'slug' => 'community-clean-up-' . uniqid(),
            'status' => 'open',
            'capacity' => 10,
        ]);

        Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Existing Helper',
            'email' => 'helper@sfu.ca',
            'status' => 'signed_up',
        ]);

        $response = $this->getJson('/api/v1/volunteering/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $opportunity->id)
            ->assertJsonPath('data.0.signups_count', 1);
    }

    /** @test */
    public function volunteer_signup_stores_signup_and_dispatches_post_commit_notification(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Friday Setup',
            'slug' => 'friday-setup-' . uniqid(),
            'status' => 'open',
            'capacity' => 5,
        ]);

        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Ahmad Student',
            'email' => 'ahmad@sfu.ca',
            'phone' => '778-999-0000',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'signed_up');

        $signupId = $response->json('data.id');

        $this->assertDatabaseHas('volunteering_signups', [
            'id' => $signupId,
            'email' => 'ahmad@sfu.ca',
            'status' => 'signed_up',
        ]);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signupId,
            'type' => 'vms_signup_confirmed',
            'recipient_email' => 'ahmad@sfu.ca',
        ]);
    }

    /** @test */
    public function volunteer_cannot_cancel_another_users_signup_idor(): void
    {
        $userA = User::factory()->create(['email' => 'usera@sfu.ca']);
        $userB = User::factory()->create(['email' => 'userb@sfu.ca']);

        $opportunity = Opportunity::create([
            'title' => 'IDOR Test Event',
            'slug' => 'idor-test-' . uniqid(),
            'status' => 'open',
        ]);

        $signupA = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $userA->id,
            'name' => 'User A',
            'email' => 'usera@sfu.ca',
            'status' => 'signed_up',
        ]);

        // User B attempts to cancel User A's signup -> Must fail 403
        $this->actingAs($userB, 'sanctum');
        $response = $this->postJson("/api/v1/volunteering/signups/{$signupA->uuid}/cancel");
        $response->assertStatus(403);

        $this->assertEquals('signed_up', $signupA->fresh()->status);
    }

    /** @test */
    public function volunteer_history_hides_admin_notes_and_exposes_attendance_status(): void
    {
        $user = User::factory()->create(['email' => 'historyuser@sfu.ca']);

        $opportunity = Opportunity::create([
            'title' => 'History Test Event',
            'slug' => 'history-test-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'name' => 'History User',
            'email' => 'historyuser@sfu.ca',
            'status' => 'confirmed',
            'attendance_status' => 'present',
            'admin_notes' => 'Internal sensitive note for registrars only',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/volunteering/my-history');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_signups', 1)
            ->assertJsonPath('data.signups.0.attendance_status', 'present');

        // Verify admin_notes is NOT exposed in the JSON response
        $signupData = $response->json('data.signups.0');
        $this->assertArrayNotHasKey('admin_notes', $signupData);
    }

    /** @test */
    public function duplicate_active_signup_for_same_opportunity_is_blocked(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Single Signup Event',
            'slug' => 'single-signup-' . uniqid(),
            'status' => 'open',
        ]);

        Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Duplicate Tester',
            'email' => 'dupe@sfu.ca',
            'status' => 'signed_up',
        ]);

        // Attempt second active signup with same email -> 422
        $response = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'name' => 'Duplicate Tester',
            'email' => 'dupe@sfu.ca',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
