<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\PaymentProvider;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use App\Ems\Support\EmsRoles;
use App\Models\Permission;

class EmsManualRegistrationTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ems.notifications.enabled' => true]);
    }

    public function test_admin_can_manually_register_attendee_with_cash_payment(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event(['status' => 'published', 'capacity' => 50]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 25.00,
            'quantity' => 10,
        ]);

        $response = $this->actingAsEms($admin)->postJson($this->url("events/{$event->uuid}/manual-registration"), [
            'first_name' => 'John',
            'last_name' => 'CashPayer',
            'email' => 'cashpayer@example.com',
            'phone' => '+17781112222',
            'registration_type' => 'cash',
            'amount' => 25.00,
            'ticket_type_id' => $ticketType->uuid,
            'quantity' => 1,
            'notes' => 'Collected $25 cash at door',
        ]);

        $this->assertSuccessEnvelope($response);
        $response->assertStatus(201);
        $this->assertEquals('John CashPayer', $response->json('data.registration.attendee_name'));
        $this->assertEquals('cashpayer@example.com', $response->json('data.registration.attendee_email'));

        $this->assertDatabaseHas('ems_orders', [
            'event_id' => $event->id,
            'total_amount' => 25.00,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('ems_payments', [
            'provider' => PaymentProvider::Cash->value,
            'amount' => 25.00,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('ems_registrations', [
            'event_id' => $event->id,
            'attendee_name' => 'John CashPayer',
            'attendee_email' => 'cashpayer@example.com',
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('cashpayer@example.com');
        });
    }

    public function test_admin_can_manually_register_invited_guest(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event(['status' => 'published', 'capacity' => 50]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 50.00,
            'quantity' => 10,
        ]);

        $response = $this->actingAsEms($admin)->postJson($this->url("events/{$event->uuid}/manual-registration"), [
            'first_name' => 'VIP',
            'last_name' => 'Speaker',
            'email' => 'vip.speaker@example.com',
            'registration_type' => 'guest_invite',
            'ticket_type_id' => $ticketType->uuid,
            'quantity' => 1,
            'notes' => 'Keynote Guest Speaker Pass',
        ]);

        $this->assertSuccessEnvelope($response);
        $response->assertStatus(201);

        $this->assertDatabaseHas('ems_payments', [
            'provider' => PaymentProvider::GuestInvite->value,
            'amount' => 0.00,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('ems_registrations', [
            'event_id' => $event->id,
            'attendee_name' => 'VIP Speaker',
            'attendee_email' => 'vip.speaker@example.com',
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('vip.speaker@example.com');
        });
    }

    public function test_ems_intelligence_includes_cash_revenue_and_guest_invites_count(): void
    {
        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $perm = Permission::firstOrCreate(['slug' => 'platform.view'], ['name' => 'Platform View', 'module' => 'platform']);
        $admin->permissions()->syncWithoutDetaching([$perm->id]);

        $response = $this->actingAsEms($admin)->getJson('/api/v1/admin/intelligence/ems?period=30d');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'financial' => [
                    'cash_revenue',
                    'guest_invites_count',
                ]
            ]
        ]);
    }

    public function test_admin_can_manually_register_attendee_without_email_address(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event(['status' => 'published', 'capacity' => 50]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 15.00,
            'quantity' => 10,
        ]);

        $response = $this->actingAsEms($admin)->postJson($this->url("events/{$event->uuid}/manual-registration"), [
            'first_name' => 'Physical',
            'last_name' => 'TicketBuyer',
            'registration_type' => 'cash',
            'amount' => 15.00,
            'ticket_type_id' => $ticketType->uuid,
            'quantity' => 1,
            'notes' => 'Handed physical printed ticket at door',
        ]);

        $this->assertSuccessEnvelope($response);
        $response->assertStatus(201);
        $this->assertEquals('Physical TicketBuyer', $response->json('data.registration.attendee_name'));
        $this->assertNull($response->json('data.registration.attendee_email'));

        $this->assertDatabaseHas('ems_registrations', [
            'event_id' => $event->id,
            'attendee_name' => 'Physical TicketBuyer',
            'attendee_email' => null,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_template_renderer_links_volunteer_signup_button_to_event_specific_opportunity(): void
    {
        $event = $this->event(['status' => 'published']);
        $opportunity = \App\Volunteering\Models\Opportunity::create([
            'event_id' => $event->id,
            'title' => 'Event Setup & Logistics',
            'slug' => 'event-setup-logistics-xyz',
            'status' => 'open',
        ]);

        $renderer = app(\App\Ems\Services\Notifications\TemplateRenderer::class);
        $context = $renderer->buildContext(null, ['event' => $event]);

        $frontendUrl = rtrim((string) config('ems.public.frontend_url'), '/');
        $expectedLink = $frontendUrl . '/volunteer/event-setup-logistics-xyz';

        $this->assertEquals($expectedLink, $context['volunteer_signup_link']);
        $this->assertEquals($expectedLink, $context['volunteer_link']);
    }
}
