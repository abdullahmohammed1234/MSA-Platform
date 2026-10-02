<?php

namespace Tests\Feature\Vms;

use App\Models\User;
use App\Services\Intelligence\VolunteerIntelligenceService;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Vms6OperationsIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private VolunteerIntelligenceService $intelligenceService;
    private VolunteerSignupService $signupService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intelligenceService = app(VolunteerIntelligenceService::class);
        $this->signupService = app(VolunteerSignupService::class);
    }

    /** @test */
    public function volunteer_intelligence_calculates_accurate_kpis_rates_and_service_hours(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Analytics Test Event',
            'slug' => 'analytics-test-' . uniqid(),
            'status' => 'open',
            'start_at' => now()->subHours(5),
            'end_at' => now()->subHours(1),
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Shift 1 (4 Hours)',
            'start_at' => now()->subHours(5),
            'end_at' => now()->subHours(1),
            'capacity' => 10,
            'status' => 'open',
        ]);

        // Signup 1: Completed (4 hours)
        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Completed Volunteer',
            'email' => 'completed@sfu.ca',
            'status' => 'completed',
            'attendance_status' => 'present',
            'created_at' => now()->subDays(2),
        ]);

        // Signup 2: Confirmed & Present (4 hours)
        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Present Volunteer',
            'email' => 'present@sfu.ca',
            'status' => 'confirmed',
            'attendance_status' => 'present',
            'created_at' => now()->subDays(2),
        ]);

        // Signup 3: No show (0 hours)
        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'No Show Volunteer',
            'email' => 'noshow@sfu.ca',
            'status' => 'no_show',
            'attendance_status' => 'absent',
            'created_at' => now()->subDays(2),
        ]);

        // Signup 4: Cancelled (0 hours)
        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Cancelled Volunteer',
            'email' => 'cancelled@sfu.ca',
            'status' => 'cancelled',
            'created_at' => now()->subDays(2),
        ]);

        $analytics = $this->intelligenceService->getAnalytics('30d');

        $this->assertEquals(4, $analytics['kpis']['total_signups']['current']);
        $this->assertEquals(1, $analytics['kpis']['completed_signups']['current']);
        $this->assertEquals(1, $analytics['kpis']['confirmed_signups']);
        $this->assertEquals(1, $analytics['kpis']['cancelled_signups']);

        // Service Hours: Signup 1 (4h) + Signup 2 (4h) = 8.0 hours
        $this->assertEquals(8.0, $analytics['kpis']['total_service_hours']['current']);

        // Rates calculations check
        $this->assertGreaterThan(0, $analytics['kpis']['attendance_rate']);
        $this->assertGreaterThan(0, $analytics['kpis']['completion_rate']);
    }

    /** @test */
    public function returning_vs_first_time_volunteers_are_classified_correctly(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Retention Event',
            'slug' => 'retention-' . uniqid(),
            'status' => 'open',
        ]);

        // Prior signup for returning volunteer (created 60 days ago)
        $priorSignup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Returning Volunteer',
            'email' => 'returning@sfu.ca',
            'status' => 'completed',
        ]);
        $priorSignup->created_at = now()->subDays(60);
        $priorSignup->save();

        // Current period signup for returning volunteer
        $currentSignup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Returning Volunteer',
            'email' => 'returning@sfu.ca',
            'status' => 'confirmed',
        ]);
        $currentSignup->created_at = now()->subDays(5);
        $currentSignup->save();

        // Current period signup for first-time volunteer
        $newSignup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'New Volunteer',
            'email' => 'newbie@sfu.ca',
            'status' => 'signed_up',
        ]);
        $newSignup->created_at = now()->subDays(2);
        $newSignup->save();

        $analytics = $this->intelligenceService->getAnalytics('30d');

        $this->assertEquals(2, $analytics['kpis']['total_volunteers']['current']);
        $this->assertEquals(1, $analytics['kpis']['first_time_volunteers']['current']);
        $this->assertEquals(1, $analytics['kpis']['returning_volunteers']['current']);
    }

    /** @test */
    public function intelligence_endpoint_enforces_rbac_permissions(): void
    {
        $adminRole = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $unauthorizedUser = User::factory()->create();

        // 1. Unauthenticated -> 401
        $this->getJson('/api/v1/admin/intelligence/volunteers')->assertStatus(401);

        // 2. Unauthorized User -> 403
        $this->actingAs($unauthorizedUser, 'sanctum');
        $this->getJson('/api/v1/admin/intelligence/volunteers')->assertStatus(403);

        // 3. Authorized Admin -> 200
        $this->actingAs($admin, 'sanctum');
        $response = $this->getJson('/api/v1/admin/intelligence/volunteers');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'kpis',
                    'attendance_breakdown',
                    'funnel',
                    'operational_summary',
                    'trends',
                ]
            ]);
    }

    /** @test */
    public function user_history_calculates_total_service_hours_correctly(): void
    {
        $user = User::factory()->create(['email' => 'houruser@sfu.ca']);

        $opportunity = Opportunity::create([
            'title' => 'Hours Calculation Event',
            'slug' => 'hours-calc-' . uniqid(),
            'status' => 'open',
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => '3 Hour Shift',
            'start_at' => now()->subHours(4),
            'end_at' => now()->subHours(1),
            'capacity' => 5,
        ]);

        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'name' => 'Hours User',
            'email' => 'houruser@sfu.ca',
            'status' => 'completed',
        ]);

        $history = $this->signupService->getUserHistory($user->id);

        $this->assertEquals(1, $history['total_signups']);
        $this->assertEquals(1, $history['completed_count']);
        $this->assertEquals(3.0, $history['total_service_hours']);
        $this->assertEquals(3.0, $history['signups'][0]['service_hours']);
    }
}
