<?php

namespace Tests\Feature\Security;

use App\Models\ApplicationAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class VolunteeringActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Opportunity $opportunity;
    private Signup $signup;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->opportunity = Opportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Iftar Setup',
            'slug' => 'iftar-setup-'.Str::random(5),
            'description' => 'Setup crew',
            'status' => 'open',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(3),
            'capacity' => 10,
        ]);

        $this->owner = User::factory()->create();
        $this->signup = Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $this->opportunity->id,
            'user_id' => $this->owner->id,
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'status' => 'signed_up',
        ]);
    }

    private function userWithRole(string $slug, array $permissions = []): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => Str::headline($slug)]);
        foreach ($permissions as $permission) {
            $role->permissions()->syncWithoutDetaching([
                Permission::firstOrCreate(['slug' => $permission], ['name' => $permission, 'module' => 'VMS'])->id,
            ]);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function createOpportunity(User $user)
    {
        return $this->actingAs($user)->postJson('/api/v1/admin/volunteering/opportunities', [
            'title' => 'New Opportunity',
            'description' => 'Created in test',
            'status' => 'draft',
            'start_at' => now()->addWeek()->toDateTimeString(),
            'end_at' => now()->addWeek()->addHours(2)->toDateTimeString(),
            'capacity' => 5,
        ]);
    }

    private function confirmSignup(User $user)
    {
        return $this->actingAs($user)->putJson("/api/v1/admin/volunteering/signups/{$this->signup->id}/status", [
            'status' => 'confirmed',
        ]);
    }

    public function test_every_volunteering_admin_route_has_an_action_check(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/volunteering/'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $checks = array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'vms.can:'));
            $this->assertNotEmpty($checks, "Route {$route->uri()} has no vms.can action check.");
        }
    }

    public function test_read_only_permission_cannot_perform_writes(): void
    {
        $viewer = $this->userWithRole('vms-viewer', ['vms.view']);

        $this->actingAs($viewer)->getJson('/api/v1/admin/volunteering/opportunities')->assertOk();
        $this->actingAs($viewer)->getJson('/api/v1/admin/volunteering/signups')->assertOk();

        $this->createOpportunity($viewer)->assertForbidden();
        $this->confirmSignup($viewer)->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/v1/admin/volunteering/opportunities/{$this->opportunity->id}")->assertForbidden();
        $this->actingAs($viewer)->get('/api/v1/admin/volunteering/export')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/admin/volunteering/skills', ['name' => 'Cooking'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/admin/volunteering/volunteers/{$this->owner->id}/award", ['achievement_id' => 1])->assertForbidden();

        $this->assertSame('signed_up', $this->signup->fresh()->status);
        $this->assertNotNull($this->opportunity->fresh());
    }

    public function test_signup_manager_can_manage_signups_but_not_opportunities(): void
    {
        $manager = $this->userWithRole('vms-signup-desk', ['vms.view', 'vms.manage_signups']);

        $this->confirmSignup($manager)->assertOk();
        $this->assertSame('confirmed', $this->signup->fresh()->status);

        $this->createOpportunity($manager)->assertForbidden();
        $this->actingAs($manager)->deleteJson("/api/v1/admin/volunteering/opportunities/{$this->opportunity->id}")->assertForbidden();
    }

    public function test_volunteer_coordinator_role_coordinates_signups_only(): void
    {
        $coordinator = $this->userWithRole('volunteer-coordinator');

        $this->actingAs($coordinator)->getJson('/api/v1/admin/volunteering/opportunities')->assertOk();
        $this->confirmSignup($coordinator)->assertOk();

        $this->createOpportunity($coordinator)->assertForbidden();
        $this->actingAs($coordinator)->postJson('/api/v1/admin/volunteering/skills', ['name' => 'Cooking'])->assertForbidden();
        $this->actingAs($coordinator)->get('/api/v1/admin/volunteering/export')->assertForbidden();
    }

    public function test_full_vms_manager_and_operators_can_create_opportunities(): void
    {
        $this->createOpportunity($this->userWithRole('vms-lead', ['vms.manage']))->assertCreated();
        $this->createOpportunity($this->userWithRole('vms-administrator'))->assertCreated();
        $this->createOpportunity($this->userWithRole('admin'))->assertCreated();

        $granted = User::factory()->create();
        ApplicationAccess::create(['user_id' => $granted->id, 'application' => 'volunteering', 'granted_by' => $granted->id]);
        $this->createOpportunity($granted)->assertCreated();
    }

    public function test_volunteers_mentors_and_dawah_coordinators_have_no_admin_access(): void
    {
        $users = [
            $this->userWithRole('volunteer'),
            $this->userWithRole('mentor', ['manage_volunteers', 'view_progress']),
            $this->userWithRole('dawah-coordinator', ['manage_volunteers']),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->getJson('/api/v1/admin/volunteering/opportunities')->assertForbidden();
            $this->confirmSignup($user)->assertForbidden();
        }

        $this->assertSame('signed_up', $this->signup->fresh()->status);
    }

    public function test_mentor_cannot_cancel_another_users_volunteer_signup(): void
    {
        $mentor = $this->userWithRole('mentor', ['manage_volunteers', 'view_progress']);

        $this->actingAs($mentor)
            ->postJson("/api/v1/volunteering/signups/{$this->signup->uuid}/cancel")
            ->assertForbidden();

        $this->assertSame('signed_up', $this->signup->fresh()->status);
    }

    public function test_signup_owner_and_signup_managers_can_still_cancel(): void
    {
        $this->actingAs($this->userWithRole('vms-signup-desk', ['vms.manage_signups']))
            ->postJson("/api/v1/volunteering/signups/{$this->signup->uuid}/cancel")
            ->assertOk();

        $this->assertSame('cancelled', $this->signup->fresh()->status);
    }
}
