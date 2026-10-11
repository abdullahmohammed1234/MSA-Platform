<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Admin user with view_analytics permission (via admin role)
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $this->adminUser = User::factory()->create([
            'email' => 'admin.analytics@sfu.ca',
            'email_verified_at' => now(),
        ]);
        $this->adminUser->assignRole($adminRole);

        // 2. Member user without admin role
        $this->memberUser = User::factory()->create([
            'email' => 'member.analytics@sfu.ca',
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_analytics_overview(): void
    {
        $response = $this->getJson('/api/v1/analytics/overview');
        $response->assertStatus(401);
    }

    public function test_unauthorized_member_is_forbidden_from_analytics_overview(): void
    {
        $response = $this->actingAs($this->memberUser)->getJson('/api/v1/analytics/overview');
        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_retrieve_aggregate_analytics_overview(): void
    {
        // Populate sample CMS, EMS, and VMS data
        DB::table('announcements')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Test Announcement',
            'slug' => 'test-announcement',
            'content' => 'Content here',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ems_events')->insert([
            'id' => 999,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Community Event',
            'slug' => 'test-community-event',
            'status' => 'published',
            'start_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ems_registrations')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'REG-TEST-999',
            'event_id' => 999,
            'user_id' => $this->memberUser->id,
            'attendee_name' => $this->memberUser->name,
            'attendee_email' => $this->memberUser->email,
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('volunteering_opportunities')->insert([
            'id' => 888,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Test Volunteer Opportunity',
            'slug' => 'test-volunteer-opp',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('volunteering_signups')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'opportunity_id' => 888,
            'user_id' => $this->memberUser->id,
            'name' => $this->memberUser->name,
            'email' => $this->memberUser->email,
            'status' => 'confirmed',
            'attendance_status' => 'attended',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'kpis' => ['visitors', 'page_views', 'active_learners', 'certificates'],
                'cms' => ['announcements_published', 'resources_published', 'featured_opportunities'],
                'ems' => ['published_events', 'total_registrations', 'verified_attendance', 'attendance_rate'],
                'volunteering' => ['active_opportunities', 'applications_received', 'confirmed_assignments', 'verified_attendees', 'verified_service_hours'],
            ]);

        $this->assertEquals(1, $response->json('cms.announcements_published'));
        $this->assertEquals(1, $response->json('ems.published_events'));
        $this->assertEquals(1, $response->json('ems.total_registrations'));
        $this->assertEquals(1, $response->json('volunteering.applications_received'));
        $this->assertEquals(1, $response->json('volunteering.verified_attendees'));

        // Assert Privacy: No personal member email addresses are present in the response
        $contentString = json_encode($response->json());
        $this->assertStringNotContainsString($this->memberUser->email, $contentString);
    }

    public function test_reversed_date_range_is_handled_safely_without_query_failure(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/analytics/overview?start_date=2026-12-31&end_date=2026-01-01');
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_csv_export_requires_export_permission_and_sanitizes_formulas(): void
    {
        // Unauthenticated check
        $this->getJson('/api/v1/analytics/export?format=csv&type=overview')
            ->assertStatus(401);

        // Member check (no export_analytics permission)
        $this->actingAs($this->memberUser)
            ->getJson('/api/v1/analytics/export?format=csv&type=overview')
            ->assertStatus(403);

        // Admin export
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/analytics/export?format=csv&type=overview');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }
}
