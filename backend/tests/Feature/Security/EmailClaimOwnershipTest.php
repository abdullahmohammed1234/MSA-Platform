<?php

namespace Tests\Feature\Security;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guest records are matched to accounts by email. An address that the account
 * holder has not proven they own must not unlock someone else's records.
 */
class EmailClaimOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_EMAIL = 'guest.attendee@sfu.ca';

    private function guestRegistration(): Registration
    {
        return Registration::factory()->create([
            'event_id' => Event::factory()->create()->id,
            'user_id' => null,
            'attendee_email' => self::GUEST_EMAIL,
            'status' => 'confirmed',
        ]);
    }

    public function test_unverified_account_cannot_see_guest_tickets_for_its_email(): void
    {
        $registration = $this->guestRegistration();
        Sanctum::actingAs(User::factory()->unverified()->create(['email' => self::GUEST_EMAIL]));

        $response = $this->getJson('/api/v1/ems/public/my-tickets')->assertOk();

        $this->assertStringNotContainsString($registration->uuid, $response->getContent());
    }

    public function test_unverified_account_cannot_cancel_guest_registration_for_its_email(): void
    {
        $registration = $this->guestRegistration();
        Sanctum::actingAs(User::factory()->unverified()->create(['email' => self::GUEST_EMAIL]));

        $this->postJson("/api/v1/ems/public/registrations/{$registration->uuid}/cancel")->assertNotFound();
        $this->assertSame('confirmed', $registration->fresh()->status->value ?? $registration->fresh()->status);
    }

    public function test_verified_account_still_sees_guest_tickets_for_its_email(): void
    {
        $registration = $this->guestRegistration();
        Sanctum::actingAs(User::factory()->create(['email' => self::GUEST_EMAIL, 'email_verified_at' => now()]));

        $this->assertStringContainsString(
            $registration->uuid,
            $this->getJson('/api/v1/ems/public/my-tickets')->assertOk()->getContent()
        );
    }

    public function test_own_registrations_are_visible_without_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $registration = Registration::factory()->create([
            'event_id' => Event::factory()->create()->id,
            'user_id' => $user->id,
            'attendee_email' => $user->email,
        ]);
        Sanctum::actingAs($user);

        $this->assertStringContainsString(
            $registration->uuid,
            $this->getJson('/api/v1/ems/public/my-tickets')->assertOk()->getContent()
        );
    }

    public function test_changing_profile_email_clears_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/profile', ['email' => self::GUEST_EMAIL])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(self::GUEST_EMAIL, $user->fresh()->email);
    }

    public function test_changing_only_the_name_keeps_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/profile', ['name' => 'New Name', 'email' => strtoupper($user->email)])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_profile_email_change_then_ticket_claim_is_blocked(): void
    {
        $registration = $this->guestRegistration();
        $attacker = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($attacker);

        $this->putJson('/api/v1/users/profile', ['email' => self::GUEST_EMAIL])->assertOk();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($attacker->fresh());

        $this->assertStringNotContainsString(
            $registration->uuid,
            $this->getJson('/api/v1/ems/public/my-tickets')->assertOk()->getContent()
        );
    }

    public function test_unverified_account_cannot_see_guest_volunteer_history_for_its_email(): void
    {
        $opportunity = Opportunity::create(['title' => 'Guest Shift', 'slug' => 'guest-shift-'.uniqid(), 'status' => 'open']);
        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => null,
            'name' => 'Guest Volunteer',
            'email' => self::GUEST_EMAIL,
            'status' => 'confirmed',
        ]);
        Sanctum::actingAs(User::factory()->unverified()->create(['email' => self::GUEST_EMAIL]));

        $this->assertStringNotContainsString(
            (string) $signup->uuid,
            $this->getJson('/api/v1/volunteering/my-history')->assertOk()->getContent()
        );
    }
}
