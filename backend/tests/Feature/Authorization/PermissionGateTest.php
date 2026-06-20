<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Feature\Authorization\PermissionGateTest.php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_has_unrestricted_access()
    {
        $user = User::where('email', 'superadmin@example.com')->first();
        $this->assertTrue($user->hasPermission('any_random_nonexistent_permission'));

        $this->actingAs($user)
            ->getJson(route('api.admin.security.dashboard'))
            ->assertStatus(200);
    }

    public function test_admin_has_management_access_but_not_unrestricted()
    {
        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($user->hasPermission('manage_courses'));

        $this->actingAs($user)
            ->getJson(route('api.admin.security.dashboard'))
            ->assertStatus(200);
    }

    public function test_director_has_events_and_analytics_only()
    {
        $user = User::factory()->create();
        $user->assignRole('director');

        $this->assertTrue($user->hasPermission('manage_events'));
        $this->assertFalse($user->hasPermission('manage_users'));

        $this->actingAs($user)
            ->getJson(route('api.admin.users.index'))
            ->assertStatus(403);
    }

    public function test_dawah_coordinator_has_academy_access()
    {
        $user = User::where('email', 'coordinator@example.com')->first();

        $this->assertTrue($user->hasPermission('manage_courses'));
        $this->assertFalse($user->hasPermission('manage_users'));

        $this->actingAs($user)
            ->getJson(route('api.admin.users.index'))
            ->assertStatus(403);
    }

    public function test_mentor_has_volunteer_viewing_access()
    {
        $user = User::where('email', 'mentor@example.com')->first();

        $this->assertTrue($user->hasPermission('manage_volunteers'));
        $this->assertFalse($user->hasPermission('manage_courses'));

        $this->actingAs($user)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }

    public function test_volunteer_has_no_admin_access()
    {
        $user = User::where('email', 'volunteer@example.com')->first();

        $this->assertFalse($user->hasPermission('manage_volunteers'));

        $this->actingAs($user)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }

    public function test_member_has_basic_community_access_only()
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->assertFalse($user->hasPermission('manage_courses'));

        $this->actingAs($user)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }

    public function test_guest_is_forbidden_from_all_endpoints()
    {
        $user = User::factory()->create();
        $user->assignRole('guest');

        $this->actingAs($user)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }
}
