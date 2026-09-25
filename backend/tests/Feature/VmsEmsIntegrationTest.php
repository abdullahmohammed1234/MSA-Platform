<?php

namespace Tests\Feature;

use App\Ems\Models\Event;
use App\Models\ApplicationAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VmsEmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $permission = Permission::firstOrCreate(['slug' => 'manage_volunteers'], ['name' => 'Manage Volunteers', 'module' => 'volunteering']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);
        $this->adminUser->permissions()->attach($permission);

        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'volunteering',
            'granted_by' => $this->adminUser->id,
        ]);
        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_list_eligible_ems_events(): void
    {
        $event = Event::factory()->create([
            'name' => 'SFU MSA Annual Gala',
            'slug' => 'sfu-msa-annual-gala',
            'location' => 'Student Union Building',
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(4),
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/volunteering/eligible-events');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $events = $response->json('data');
        $this->assertNotEmpty($events);
        $this->assertEquals('SFU MSA Annual Gala', $events[0]['name']);
        $this->assertFalse($events[0]['is_configured']);
    }

    public function test_admin_can_enable_volunteering_for_ems_event(): void
    {
        $event = Event::factory()->create([
            'name' => 'Ramadan Iftar Setup',
            'slug' => 'ramadan-iftar-setup',
            'description' => 'Help setup the hall for community Iftar.',
            'location' => 'SFU Multi-Faith Centre',
            'start_at' => now()->addDays(10),
            'end_at' => now()->addDays(10)->addHours(3),
        ]);

        $payload = [
            'event_id' => $event->id,
            'title' => 'Iftar Setup Team',
            'status' => 'open',
            'teams' => [
                [
                    'name' => 'Hall Setup',
                    'description' => 'Arrange tables and chairs',
                    'capacity' => 15,
                    'shifts' => [
                        [
                            'name' => 'Shift 1',
                            'start_at' => now()->addDays(10)->toIso8601String(),
                            'end_at' => now()->addDays(10)->addHours(2)->toIso8601String(),
                            'capacity' => 10,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/volunteering/opportunities', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_id' => $event->id,
                    'title' => 'Iftar Setup Team',
                    'status' => 'open',
                ],
            ]);

        $this->assertDatabaseHas('volunteering_opportunities', [
            'event_id' => $event->id,
            'title' => 'Iftar Setup Team',
            'status' => 'open',
        ]);
    }

    public function test_public_user_can_view_ems_backed_opportunities(): void
    {
        $event = Event::factory()->create([
            'name' => 'Jumuah Volunteering',
            'slug' => 'jumuah-volunteering',
        ]);

        $opportunity = Opportunity::create([
            'event_id' => $event->id,
            'title' => 'Jumuah Logistics Team',
            'slug' => 'jumuah-logistics-team',
            'description' => 'Assist with carpet setup and audio system.',
            'status' => 'open',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
            'location' => 'SUB Ballroom',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/volunteering/opportunities');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Jumuah Logistics Team', $data[0]['title']);
        $this->assertEquals($event->id, $data[0]['event']['id']);
    }
}
