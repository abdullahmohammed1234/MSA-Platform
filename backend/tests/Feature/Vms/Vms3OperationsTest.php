<?php

namespace Tests\Feature\Vms;

use App\Models\Role;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Vms3OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        $this->admin = User::factory()->create([
            'email' => 'admin@sfu.ca',
        ]);
        $this->admin->assignRole($adminRole);

        $this->volunteer = User::factory()->create([
            'email' => 'volunteer@sfu.ca',
        ]);
    }

    /** @test */
    public function unauthorized_users_cannot_access_vms3_admin_endpoints(): void
    {
        $this->actingAs($this->volunteer, 'sanctum');

        $res1 = $this->getJson('/api/v1/admin/volunteering/signups');
        $res1->assertStatus(403);

        $res2 = $this->putJson('/api/v1/admin/volunteering/signups/1/attendance', [
            'attendance_status' => 'present',
        ]);
        $res2->assertStatus(403);

        $res3 = $this->postJson('/api/v1/admin/volunteering/signups/1/promote');
        $res3->assertStatus(403);

        $res4 = $this->getJson('/api/v1/admin/volunteering/export');
        $res4->assertStatus(403);
    }

    /** @test */
    public function admin_can_list_all_signups_with_search_and_filtering(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'VMS3 Filter Test Event',
            'slug' => 'vms3-filter-' . uniqid(),
            'status' => 'open',
        ]);

        $signup1 = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Alice Smith',
            'email' => 'alice@sfu.ca',
            'status' => 'confirmed',
            'attendance_status' => 'present',
        ]);

        $signup2 = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Bob Jones',
            'email' => 'bob@sfu.ca',
            'status' => 'signed_up',
            'attendance_status' => 'not_marked',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        // Global list
        $response = $this->getJson('/api/v1/admin/volunteering/signups');
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // Search by name
        $searchRes = $this->getJson('/api/v1/admin/volunteering/signups?search=Alice');
        $searchRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alice Smith');

        // Filter by attendance status
        $attendanceRes = $this->getJson('/api/v1/admin/volunteering/signups?attendance_status=present');
        $attendanceRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $signup1->id);
    }

    /** @test */
    public function admin_can_update_single_and_batch_attendance(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Attendance Test Event',
            'slug' => 'att-test-' . uniqid(),
            'status' => 'open',
        ]);

        $signup1 = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Attendance User 1',
            'email' => 'att1@sfu.ca',
            'status' => 'signed_up',
            'attendance_status' => 'not_marked',
        ]);

        $signup2 = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Attendance User 2',
            'email' => 'att2@sfu.ca',
            'status' => 'signed_up',
            'attendance_status' => 'not_marked',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        // Single update via PUT
        $res1 = $this->putJson("/api/v1/admin/volunteering/signups/{$signup1->id}/attendance", [
            'attendance_status' => 'present',
        ]);
        $res1->assertStatus(200);

        $this->assertEquals('present', $signup1->fresh()->attendance_status);
        $this->assertEquals('confirmed', $signup1->fresh()->status);
        $this->assertNotNull($signup1->fresh()->attended_at);

        // Batch update via POST
        $res2 = $this->postJson('/api/v1/admin/volunteering/signups/batch-attendance', [
            'signup_ids' => [$signup2->id],
            'attendance_status' => 'absent',
        ]);
        $res2->assertStatus(200);

        $this->assertEquals('absent', $signup2->fresh()->attendance_status);
    }

    /** @test */
    public function admin_can_promote_waitlisted_signup_and_dispatch_transactional_notification(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Waitlist Promo Event',
            'slug' => 'waitlist-promo-' . uniqid(),
            'status' => 'open',
            'capacity' => 5,
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Waitlisted Person',
            'email' => 'waitlisted@sfu.ca',
            'status' => 'waitlisted',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/v1/admin/volunteering/signups/{$signup->id}/promote");
        $response->assertStatus(200);

        $this->assertEquals('confirmed', $signup->fresh()->status);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_waitlist_promoted',
            'recipient_email' => 'waitlisted@sfu.ca',
        ]);
    }

    /** @test */
    public function admin_can_export_volunteers_csv_with_formula_sanitization(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'CSV Export Event',
            'slug' => 'csv-export-' . uniqid(),
            'status' => 'open',
        ]);

        Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => '=CmdInjection',
            'email' => 'safe@sfu.ca',
            'status' => 'confirmed',
            'attendance_status' => 'present',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/v1/admin/volunteering/export?opportunity_id={$opportunity->id}");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();

        // Formula injection check: '=' should be prepended with a single quote "'"
        $this->assertStringContainsString("'=CmdInjection", $content);
        $this->assertStringContainsString("safe@sfu.ca", $content);
    }
}
