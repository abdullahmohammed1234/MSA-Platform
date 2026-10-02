<?php

namespace Tests\Feature\Vms;

use App\Ems\Models\EventNotification;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use App\Volunteering\Models\VolunteerInvitation;
use App\Volunteering\Models\VolunteerProfile;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Vms9HardeningAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Event::fake();
    }

    private function makeVms9AdminUser(): User
    {
        $user = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'VMS Admin',
            'email' => 'admin_vms9_' . Str::random(6) . '@sfu.ca',
        ]);

        \App\Models\ApplicationAccess::create([
            'user_id' => $user->id,
            'application' => 'volunteering',
            'granted_by' => $user->id,
        ]);

        return $user;
    }

    private function makeVms9VolunteerUser(string $name = 'Volunteer User', ?string $email = null): User
    {
        return User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email ?? ('volunteer_vms9_' . Str::random(6) . '@sfu.ca'),
        ]);
    }

    private function makeVms9Opportunity(array $override = []): Opportunity
    {
        return Opportunity::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'title' => 'VMS-9 Hardened Event',
            'slug' => 'vms9-hardened-event-' . Str::random(6),
            'description' => 'Security and concurrency testing opportunity.',
            'status' => 'open',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(4),
            'capacity' => 10,
        ], $override));
    }

    /**
     * Test 1: Authentication & Authorization boundaries on VMS routes.
     */
    public function test_authentication_and_authorization_boundaries(): void
    {
        $volunteer = $this->makeVms9VolunteerUser();

        // 1. Unauthenticated request to private profile route fails
        $this->getJson('/api/v1/volunteering/profile')
            ->assertStatus(401);

        // 2. Volunteer user access to admin routes is forbidden
        $this->actingAs($volunteer)
            ->getJson('/api/v1/admin/volunteering/opportunities')
            ->assertStatus(403);

        // 3. Authorized admin user can access admin opportunities
        $admin = $this->makeVms9AdminUser();
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/volunteering/opportunities')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test 2: IDOR and cross-user resource manipulation protection.
     */
    public function test_idor_and_cross_user_resource_protection(): void
    {
        $userA = $this->makeVms9VolunteerUser('User A', 'usera@sfu.ca');
        $userB = $this->makeVms9VolunteerUser('User B', 'userb@sfu.ca');

        $opportunity = $this->makeVms9Opportunity();

        // Create signup for User A
        $signupA = Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'user_id' => $userA->id,
            'name' => $userA->name,
            'email' => $userA->email,
            'status' => 'signed_up',
        ]);

        // User B attempts to cancel User A's signup via IDOR
        $this->actingAs($userB)
            ->postJson("/api/v1/volunteering/signups/{$signupA->uuid}/cancel")
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // User A's signup remains active
        $this->assertEquals('signed_up', $signupA->fresh()->status);

        // Invitation IDOR: Create invitation for User A
        $invitation = VolunteerInvitation::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'user_id' => $userA->id,
            'invited_by' => $userA->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(5),
        ]);

        // User B attempts to accept User A's invitation
        $this->actingAs($userB)
            ->postJson("/api/v1/volunteering/invitations/{$invitation->uuid}/accept")
            ->assertStatus(404);

        $this->assertEquals('pending', $invitation->fresh()->status);
    }

    /**
     * Test 3: Capacity locking & waitlist enforcement under zero capacity leak.
     */
    public function test_capacity_and_waitlist_enforcement(): void
    {
        $opportunity = $this->makeVms9Opportunity(['capacity' => 1]);
        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Single Slot Shift',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHours(2),
            'capacity' => 1,
            'status' => 'open',
        ]);

        $service = app(VolunteerSignupService::class);

        // Signup 1: Takes slot 1
        $signup1 = $service->registerSignup([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Vol 1',
            'email' => 'vol1@sfu.ca',
        ]);

        $this->assertEquals('signed_up', $signup1->status);

        // Signup 2 without waitlist flag fails
        try {
            $service->registerSignup([
                'opportunity_id' => $opportunity->id,
                'shift_id' => $shift->id,
                'name' => 'Vol 2',
                'email' => 'vol2@sfu.ca',
            ]);
            $this->fail('Expected capacity exception was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('shift_id', $e->errors());
        }

        // Signup 2 with join_waitlist=true joins waitlist
        $signup2 = $service->registerSignup([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Vol 2',
            'email' => 'vol2@sfu.ca',
            'join_waitlist' => true,
        ]);

        $this->assertEquals('waitlisted', $signup2->status);

        // Cancel Signup 1 -> Waitlisted Signup 2 should be automatically promoted
        $service->cancelSignup($signup1);

        $this->assertEquals('cancelled', $signup1->fresh()->status);
        $this->assertEquals('signed_up', $signup2->fresh()->status);
    }

    /**
     * Test 4: Transaction rollback produces zero queued notifications.
     */
    public function test_transaction_rollback_suppresses_notifications(): void
    {
        $opportunity = $this->makeVms9Opportunity();
        $service = app(VolunteerSignupService::class);

        try {
            DB::transaction(function () use ($service, $opportunity) {
                $service->registerSignup([
                    'opportunity_id' => $opportunity->id,
                    'name' => 'Rollback Volunteer',
                    'email' => 'rollback@sfu.ca',
                ]);

                // Force transaction failure
                throw new \RuntimeException('Simulated DB Exception');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Verify zero notifications queued in EventNotification ledger for this email
        $this->assertDatabaseMissing('ems_notifications', [
            'recipient_email' => 'rollback@sfu.ca',
        ]);

        $this->assertDatabaseMissing('volunteering_signups', [
            'email' => 'rollback@sfu.ca',
        ]);
    }

    /**
     * Test 5: Volunteer privacy controls (hiding internal notes).
     */
    public function test_privacy_controls_hide_admin_notes_from_volunteers(): void
    {
        $volunteer = $this->makeVms9VolunteerUser();
        $admin = $this->makeVms9AdminUser();

        $opportunity = $this->makeVms9Opportunity();

        $signup = Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'name' => $volunteer->name,
            'email' => $volunteer->email,
            'status' => 'signed_up',
            'admin_notes' => 'INTERNAL SENSITIVE ADMIN NOTE',
            'processed_by' => $admin->id,
        ]);

        // History endpoint for volunteer hides admin_notes
        $res = $this->actingAs($volunteer)->getJson('/api/v1/volunteering/my-history');
        $res->assertStatus(200);

        $responseContent = json_encode($res->json());
        $this->assertStringNotContainsString('INTERNAL SENSITIVE ADMIN NOTE', $responseContent);
    }

    /**
     * Test 6: Input validation & state transition safety.
     */
    public function test_invalid_state_transitions_are_rejected(): void
    {
        $admin = $this->makeVms9AdminUser();
        $opportunity = $this->makeVms9Opportunity();

        $signup = Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'name' => 'Test State Vol',
            'email' => 'statevol@sfu.ca',
            'status' => 'completed',
        ]);

        // Attempting to move completed back to confirmed is rejected
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/volunteering/signups/{$signup->id}/status", [
                'status' => 'confirmed',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertEquals('completed', $signup->fresh()->status);
    }
}
