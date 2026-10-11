<?php

namespace Tests\Feature\Account;

use App\Ems\Models\Event;
use App\Ems\Models\Registration as EmsRegistration;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use App\Store\Models\StoreOrder;
use App\Volunteering\Models\Signup as VolunteeringSignup;
use App\Volunteering\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemPermissionSeeder::class);
    }

    public function test_unauthenticated_request_to_summary_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/account/summary');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_account_summary(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Member',
            'email' => 'jane@sfu.ca',
            'email_verified_at' => now(),
        ]);

        $memberRole = Role::where('slug', 'member')->first();
        if ($memberRole) {
            $user->roles()->attach($memberRole->id);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/account/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Jane Member')
            ->assertJsonPath('data.user.email', 'jane@sfu.ca')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.community_status', 'Active Member');
    }

    public function test_user_a_does_not_see_user_b_data(): void
    {
        $userA = User::factory()->create(['email' => 'usera@sfu.ca']);
        $userB = User::factory()->create(['email' => 'userb@sfu.ca']);

        $event = Event::factory()->create();

        // Create EMS registration for User B
        EmsRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $userB->id,
            'attendee_email' => $userB->email,
        ]);

        // Create Store order for User B
        StoreOrder::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $userB->id,
            'customer_name' => 'User B',
            'customer_email' => $userB->email,
            'subtotal' => 50.00,
            'tax' => 2.50,
            'total_amount' => 52.50,
            'payment_status' => 'paid',
            'fulfillment_status' => 'pending',
        ]);

        // Act as User A
        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/account/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.counts.upcoming_events', 0)
            ->assertJsonPath('data.counts.orders', 0);
    }

    public function test_guest_records_matching_email_are_excluded_from_account_summary(): void
    {
        $user = User::factory()->create(['email' => 'member@sfu.ca']);

        $event = Event::factory()->create();

        // Guest EMS Registration (user_id IS NULL, but matching email)
        EmsRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => null,
            'attendee_email' => 'member@sfu.ca',
            'status' => 'confirmed',
        ]);

        // Guest Store Order (user_id IS NULL, but matching email)
        StoreOrder::create([
            'order_number' => 'ORD-GUEST-001',
            'user_id' => null,
            'customer_name' => 'Guest Member',
            'customer_email' => 'member@sfu.ca',
            'subtotal' => 25.00,
            'tax' => 1.25,
            'total_amount' => 26.25,
            'payment_status' => 'paid',
            'fulfillment_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/account/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.counts.upcoming_events', 0)
            ->assertJsonPath('data.counts.orders', 0)
            ->assertJsonCount(0, 'data.activity.events')
            ->assertJsonCount(0, 'data.activity.orders');
    }

    public function test_password_update_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/account/password', [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password updated successfully.')
            ->assertJsonStructure(['token']);

        $this->assertTrue(Hash::check('NewSecretPassword123!', $user->fresh()->password));
    }

    public function test_password_update_fails_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/account/password', [
            'current_password' => 'WrongPassword!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }
}
