<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The volunteer role is self-service at registration, so it must never open
 * the volunteering administration API.
 */
class VolunteeringAdminBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_READS = [
        '/api/v1/admin/volunteering/export',
        '/api/v1/admin/volunteering/signups',
        '/api/v1/admin/volunteering/profiles',
        '/api/v1/admin/volunteering/opportunities',
        '/api/v1/admin/volunteering/analytics',
        '/api/v1/admin/volunteering/retention',
    ];

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['email' => $slug.uniqid().'@sfu.ca']);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]));

        return $user->fresh();
    }

    public function test_self_registered_volunteer_cannot_reach_volunteering_admin_api(): void
    {
        Notification::fake();
        Role::firstOrCreate(['slug' => 'volunteer'], ['name' => 'Volunteer']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Self Serve',
            'email' => 'selfserve@sfu.ca',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => true,
            'role' => 'volunteer',
        ])->assertSuccessful();

        $user = User::where('email', 'selfserve@sfu.ca')->firstOrFail();
        $this->assertTrue($user->hasRole('volunteer'));
        Sanctum::actingAs($user);

        foreach (self::ADMIN_READS as $uri) {
            $this->getJson($uri)->assertForbidden();
        }
    }

    public function test_volunteer_role_cannot_mutate_volunteering_admin_records(): void
    {
        $volunteer = $this->userWithRole('volunteer');
        $target = User::factory()->create();
        Sanctum::actingAs($volunteer);

        $this->putJson('/api/v1/admin/volunteering/signups/1/status', ['status' => 'cancelled'])->assertForbidden();
        $this->postJson("/api/v1/admin/volunteering/volunteers/{$target->id}/award", ['achievement_id' => 1])->assertForbidden();
        $this->postJson('/api/v1/admin/volunteering/opportunities', ['title' => 'x'])->assertForbidden();
    }

    public function test_volunteer_role_has_no_volunteering_or_vms_application_access(): void
    {
        $service = app(ApplicationAccessService::class);
        $volunteer = $this->userWithRole('volunteer');

        $this->assertFalse($service->canAccess($volunteer, 'volunteering'));
        $this->assertFalse($service->canAccess($volunteer, 'vms'));
        $this->assertTrue($service->canAccess($volunteer, 'dawah-academy'));
    }

    public function test_volunteering_staff_roles_keep_access(): void
    {
        foreach (['volunteer-coordinator', 'vms-manager', 'vms-administrator', 'volunteer-administrator'] as $slug) {
            Sanctum::actingAs($this->userWithRole($slug));

            $this->getJson('/api/v1/admin/volunteering/opportunities')->assertOk();
        }
    }

    public function test_explicit_application_grant_still_works_for_a_volunteer(): void
    {
        $volunteer = $this->userWithRole('volunteer');
        app(ApplicationAccessService::class)->grant($volunteer, 'volunteering');
        Sanctum::actingAs($volunteer->fresh());

        $this->getJson('/api/v1/admin/volunteering/opportunities')->assertOk();
    }
}
