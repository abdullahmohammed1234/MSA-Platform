<?php

namespace Tests\Feature\Volunteering;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_public_opportunities(): void
    {
        Opportunity::create([
            'title' => 'Friday Jumuah Setup',
            'slug' => 'jumuah-setup',
            'description' => 'Help setup prayer carpets and audio.',
            'status' => 'open',
            'start_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/volunteering/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_signup_for_shift_with_capacity_check(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Ramadan Iftar Distribution',
            'slug' => 'ramadan-iftar',
            'status' => 'open',
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Evening Shift',
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(3),
            'capacity' => 1,
            'status' => 'open',
        ]);

        // First signup succeeds
        $res1 = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Volunteer 1',
            'email' => 'vol1@example.com',
        ]);
        $res1->assertStatus(201);

        // Second signup exceeds shift capacity
        $res2 = $this->postJson('/api/v1/volunteering/signups', [
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Volunteer 2',
            'email' => 'vol2@example.com',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['shift_id']);
    }

    public function test_admin_can_manage_opportunities_and_update_signup_status(): void
    {
        \App\Models\Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $opportunity = Opportunity::create([
            'title' => 'Annual Gala Logistics',
            'slug' => 'gala-logistics',
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Helper Person',
            'email' => 'helper@example.com',
            'status' => 'signed_up',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/volunteering/signups/{$signup->id}/status", [
                'status' => 'confirmed',
                'admin_notes' => 'Vetted and verified.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('volunteering_signups', [
            'id' => $signup->id,
            'status' => 'confirmed',
            'admin_notes' => 'Vetted and verified.',
        ]);
    }
}
