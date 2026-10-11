<?php

namespace Tests\Feature\Dashboard;

use App\Ems\Models\Event;
use App\Ems\Models\Registration as EmsRegistration;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Store\Models\StoreOrder;
use App\Volunteering\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_request_to_dashboard_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_dashboard_payload(): void
    {
        $user = User::factory()->create([
            'name' => 'Abdalla Member',
            'email' => 'abdalla@sfu.ca',
            'email_verified_at' => now(),
        ]);

        $memberRole = Role::where('slug', 'member')->first();
        if ($memberRole) {
            $user->roles()->attach($memberRole->id);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Abdalla Member')
            ->assertJsonPath('user.email', 'abdalla@sfu.ca')
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('user.community_status', 'Active Member');
    }

    public function test_user_a_does_not_see_user_b_dashboard_data(): void
    {
        $userA = User::factory()->create(['email' => 'usera@sfu.ca']);
        $userB = User::factory()->create(['email' => 'userb@sfu.ca']);

        $event = Event::factory()->create([
            'start_at' => now()->addDays(2),
        ]);

        // Registration for User B
        EmsRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $userB->id,
            'attendee_email' => $userB->email,
            'status' => 'confirmed',
        ]);

        // Order for User B
        StoreOrder::create([
            'order_number' => 'ORD-USERB-001',
            'user_id' => $userB->id,
            'customer_name' => 'User B',
            'customer_email' => $userB->email,
            'subtotal_cents' => 5000,
            'tax_cents' => 250,
            'total_cents' => 5250,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);

        // Notification for User B
        Notification::create([
            'user_id' => $userB->id,
            'type' => 'event',
            'title' => 'User B Ticket Confirmed',
            'message' => 'Your ticket for User B event is ready.',
        ]);

        // Act as User A
        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('next_event', null)
            ->assertJsonPath('upcoming_events_count', 0)
            ->assertJsonPath('recent_order', null)
            ->assertJsonPath('notifications.unread_count', 0);
    }

    public function test_guest_records_matching_email_are_excluded_from_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'member@sfu.ca']);
        $event = Event::factory()->create(['start_at' => now()->addDays(1)]);

        // Guest Registration (user_id IS NULL, matching email)
        EmsRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => null,
            'attendee_email' => 'member@sfu.ca',
            'status' => 'confirmed',
        ]);

        // Guest Store Order (user_id IS NULL, matching email)
        StoreOrder::create([
            'order_number' => 'ORD-GUEST-999',
            'user_id' => null,
            'customer_name' => 'Guest',
            'customer_email' => 'member@sfu.ca',
            'subtotal_cents' => 2000,
            'tax_cents' => 100,
            'total_cents' => 2100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('next_event', null)
            ->assertJsonPath('upcoming_events_count', 0)
            ->assertJsonPath('recent_order', null);
    }

    public function test_action_center_generates_correct_tasks(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null, // Unverified
        ]);

        // Add volunteer profile with <100% completion
        VolunteerProfile::create([
            'user_id' => $user->id,
            'profile_completion_percentage' => 40,
        ]);

        // Add unread notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Welcome Notification',
            'message' => 'Welcome to SFU MSA!',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200);

        $actionIds = collect($response->json('action_items'))->pluck('id')->toArray();
        $this->assertContains('verify_email', $actionIds);
        $this->assertContains('complete_volunteer_profile', $actionIds);
        $this->assertContains('unread_notifications', $actionIds);
    }

    public function test_empty_state_returns_valid_structure(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('next_event', null)
            ->assertJsonPath('upcoming_events_count', 0)
            ->assertJsonPath('volunteer', null)
            ->assertJsonPath('recent_order', null)
            ->assertJsonPath('notifications.unread_count', 0);
    }

    public function test_administrator_user_receives_valid_dashboard_data_without_errors(): void
    {
        $adminUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@sfumsa.ca',
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::where('slug', 'admin')->first() ?? Role::where('slug', 'super-admin')->first();
        if ($adminRole) {
            $adminUser->roles()->attach($adminRole->id);
        }

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Super Admin')
            ->assertJsonPath('user.email', 'admin@sfumsa.ca')
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('user.community_status', 'Administrator')
            ->assertJsonPath('next_event', null)
            ->assertJsonPath('recent_order', null);
    }
}
