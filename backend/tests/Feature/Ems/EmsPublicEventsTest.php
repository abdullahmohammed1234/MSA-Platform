<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use Illuminate\Support\Str;

/**
 * Phase 2 — public discovery, free registration, tickets and validation.
 */
class EmsPublicEventsTest extends EmsTestCase
{
    protected function publicUrl(string $path = ''): string
    {
        return $this->url('public/' . ltrim($path, '/'));
    }

    protected function publicEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()
            ->publiclyDiscoverable()
            ->create(array_merge([
                'category_id' => $category->id,
                'name' => 'Welcome Night',
                'slug' => 'welcome-night-' . Str::lower(Str::random(4)),
                'capacity' => 50,
            ], $attributes));
    }

    public function test_lists_only_public_discoverable_events(): void
    {
        $visible = $this->publicEvent();
        $draft = Event::factory()->create(['is_public' => true, 'status' => EventStatus::Draft]);
        $private = Event::factory()->status(EventStatus::RegistrationOpen)->create(['is_public' => false]);
        $archived = Event::factory()->status(EventStatus::Archived)->create(['is_public' => true]);

        $response = $this->getJson($this->publicUrl('events'));

        $this->assertSuccessEnvelope($response);
        $slugs = collect($response->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains($visible->slug));
        $this->assertFalse($slugs->contains($draft->slug));
        $this->assertFalse($slugs->contains($private->slug));
        $this->assertFalse($slugs->contains($archived->slug));
        $this->assertArrayNotHasKey('available_transitions', $response->json('data.0'));
        $this->assertArrayNotHasKey('created_by', $response->json('data.0'));
    }

    public function test_search_and_category_filters(): void
    {
        $category = $this->category(['name' => 'Community', 'slug' => 'community']);
        $other = $this->category(['name' => 'Education', 'slug' => 'education']);

        $this->publicEvent([
            'category_id' => $category->id,
            'name' => 'Charity Dinner',
            'slug' => 'charity-dinner',
            'location' => 'AQ 3000',
        ]);
        $this->publicEvent([
            'category_id' => $other->id,
            'name' => 'Study Circle',
            'slug' => 'study-circle',
        ]);

        $byName = $this->getJson($this->publicUrl('events?search=Charity'));
        $this->assertSuccessEnvelope($byName);
        $this->assertCount(1, $byName->json('data'));
        $this->assertSame('charity-dinner', $byName->json('data.0.slug'));

        $byLocation = $this->getJson($this->publicUrl('events?search=AQ%203000'));
        $this->assertCount(1, $byLocation->json('data'));

        $byCategory = $this->getJson($this->publicUrl('events?category_slug=community'));
        $this->assertCount(1, $byCategory->json('data'));
        $this->assertSame('charity-dinner', $byCategory->json('data.0.slug'));
    }

    public function test_upcoming_and_past_filters(): void
    {
        $upcoming = $this->publicEvent([
            'slug' => 'upcoming-event',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
        ]);
        $past = $this->publicEvent([
            'slug' => 'past-event',
            'start_at' => now()->subWeeks(2),
            'end_at' => now()->subWeeks(2)->addHours(2),
            'status' => EventStatus::Completed,
            'completed_at' => now()->subWeeks(2),
            'published_at' => now()->subMonth(),
            'registration_open_at' => now()->subMonth(),
            'registration_closed_at' => now()->subWeeks(3),
        ]);

        $upcomingResponse = $this->getJson($this->publicUrl('events?upcoming=1'));
        $upcomingSlugs = collect($upcomingResponse->json('data'))->pluck('slug');
        $this->assertTrue($upcomingSlugs->contains($upcoming->slug));
        $this->assertFalse($upcomingSlugs->contains($past->slug));

        // Axios-style query strings send "true"/"false" — those must also work.
        $this->getJson($this->publicUrl('events?upcoming=true&sort_by=start_at&sort_direction=asc'))
            ->assertOk();

        $pastResponse = $this->getJson($this->publicUrl('events?past=1'));
        $pastSlugs = collect($pastResponse->json('data'))->pluck('slug');
        $this->assertTrue($pastSlugs->contains($past->slug));
    }

    public function test_registration_open_and_closed_filters(): void
    {
        $open = $this->publicEvent(['slug' => 'open-event']);
        $closed = $this->publicEvent([
            'slug' => 'closed-event',
            'status' => EventStatus::RegistrationClosed,
            'registration_closed_at' => now(),
        ]);

        $openResponse = $this->getJson($this->publicUrl('events?registration_open=1'));
        $this->assertTrue(collect($openResponse->json('data'))->pluck('slug')->contains($open->slug));
        $this->assertFalse(collect($openResponse->json('data'))->pluck('slug')->contains($closed->slug));

        $closedResponse = $this->getJson($this->publicUrl('events?registration_closed=1'));
        $this->assertTrue(collect($closedResponse->json('data'))->pluck('slug')->contains($closed->slug));
    }

    public function test_show_returns_event_by_slug_and_hides_drafts(): void
    {
        $event = $this->publicEvent(['slug' => 'landing-page-event', 'description' => 'Full details here.']);

        $response = $this->getJson($this->publicUrl('events/landing-page-event'));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.slug', 'landing-page-event');
        $response->assertJsonPath('data.description', 'Full details here.');
        $response->assertJsonPath('data.is_accepting_registrations', true);

        Event::factory()->create(['slug' => 'secret-draft', 'is_public' => true, 'status' => EventStatus::Draft]);
        $hidden = $this->getJson($this->publicUrl('events/secret-draft'));
        $hidden->assertStatus(404);
        $this->assertErrorEnvelope($hidden);
    }

    public function test_categories_endpoint(): void
    {
        $active = $this->category(['name' => 'Social', 'slug' => 'social', 'is_active' => true]);
        $this->category(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);
        $this->publicEvent(['category_id' => $active->id]);

        $response = $this->getJson($this->publicUrl('categories'));
        $this->assertSuccessEnvelope($response);

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('social'));
        $this->assertFalse($slugs->contains('inactive'));
    }

    public function test_calendar_returns_events_in_window(): void
    {
        $this->publicEvent([
            'slug' => 'cal-event',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
        ]);

        $response = $this->getJson($this->publicUrl('events/calendar?' . http_build_query([
            'starts_after' => now()->toIso8601String(),
            'starts_before' => now()->addWeek()->toIso8601String(),
        ])));

        $this->assertSuccessEnvelope($response);
        $this->assertTrue(collect($response->json('data'))->pluck('slug')->contains('cal-event'));
    }

    public function test_calendar_includes_multi_day_events_that_overlap_the_window(): void
    {
        $this->publicEvent([
            'slug' => 'retreat',
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(3),
        ]);
        $this->publicEvent([
            'slug' => 'already-over',
            'start_at' => now()->subWeeks(3),
            'end_at' => now()->subWeeks(3)->addDays(2),
        ]);

        $response = $this->getJson($this->publicUrl('events/calendar?' . http_build_query([
            'starts_after' => now()->startOfDay()->toIso8601String(),
            'starts_before' => now()->addWeek()->toIso8601String(),
        ])));

        $this->assertSuccessEnvelope($response);
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('retreat'));
        $this->assertFalse($slugs->contains('already-over'));
    }

    public function test_free_registration_creates_ticket_and_qr(): void
    {
        $event = $this->publicEvent(['slug' => 'reg-event', 'capacity' => 10]);

        $response = $this->postJson($this->publicUrl('events/reg-event/register'), [
            'first_name' => 'Amina',
            'last_name' => 'Hassan',
            'email' => 'amina@example.com',
            'phone' => '604-555-0100',
            'student_id' => '301234567',
            'notes' => 'First time guest',
        ]);

        $response->assertStatus(201);
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.status', 'confirmed');
        $response->assertJsonPath('data.status_label', 'Registered');
        $response->assertJsonPath('data.attendee_name', 'Amina Hassan');
        $this->assertNotEmpty($response->json('data.tickets'));

        $ticketCode = $response->json('data.tickets.0.code');
        $this->assertMatchesRegularExpression('/^MSA-[0-9A-Z]+$/', $ticketCode);
        $this->assertNotEmpty($response->json('data.tickets.0.qr_payload'));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $response->json('data.tickets.0.qr_image'));

        $this->assertDatabaseHas('ems_registrations', [
            'attendee_email' => 'amina@example.com',
            'event_id' => $event->id,
            'status' => 'confirmed',
            'type' => 'free',
        ]);

        $this->assertDatabaseHas('ems_tickets', [
            'code' => $ticketCode,
            'event_id' => $event->id,
            'status' => TicketStatus::Issued->value,
        ]);
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $event = $this->publicEvent(['slug' => 'dup-event']);

        $payload = [
            'first_name' => 'Omar',
            'last_name' => 'Ali',
            'email' => 'omar@example.com',
        ];

        $this->postJson($this->publicUrl('events/dup-event/register'), $payload)->assertStatus(201);

        $duplicate = $this->postJson($this->publicUrl('events/dup-event/register'), $payload);
        $duplicate->assertStatus(409);
        $this->assertErrorEnvelope($duplicate);
        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
    }

    public function test_registration_rejected_when_closed_or_full(): void
    {
        $closed = $this->publicEvent([
            'slug' => 'closed-reg',
            'status' => EventStatus::RegistrationClosed,
            'registration_closed_at' => now(),
        ]);

        $closedResponse = $this->postJson($this->publicUrl('events/closed-reg/register'), [
            'first_name' => 'Sara',
            'last_name' => 'Noor',
            'email' => 'sara@example.com',
        ]);
        $closedResponse->assertStatus(409);

        $full = $this->publicEvent(['slug' => 'full-event', 'capacity' => 1]);
        Registration::factory()->create([
            'event_id' => $full->id,
            'quantity' => 1,
            'attendee_email' => 'taken@example.com',
        ]);

        $fullResponse = $this->postJson($this->publicUrl('events/full-event/register'), [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'new@example.com',
        ]);
        $fullResponse->assertStatus(409);
    }

    public function test_registration_validation_errors(): void
    {
        $this->publicEvent(['slug' => 'validate-reg']);

        $response = $this->postJson($this->publicUrl('events/validate-reg/register'), [
            'first_name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $this->assertErrorEnvelope($response);
        $response->assertJsonValidationErrors(['first_name', 'last_name', 'email'], 'errors');
    }

    public function test_ticket_page_and_validation_endpoint(): void
    {
        $event = $this->publicEvent(['slug' => 'ticket-event']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'attendee_name' => 'Fatima Khan',
            'attendee_email' => 'fatima@example.com',
        ]);
        $ticket = Ticket::factory()->forRegistration($registration)->create([
            'code' => 'MSA-TESTTICKET1',
            'qr_payload' => 'MSA-TESTTICKET1',
        ]);

        $show = $this->getJson($this->publicUrl('tickets/MSA-TESTTICKET1'));
        $this->assertSuccessEnvelope($show);
        $show->assertJsonPath('data.code', 'MSA-TESTTICKET1');
        $show->assertJsonPath('data.event.slug', 'ticket-event');
        $this->assertArrayNotHasKey('holder_email', $show->json('data'));

        $valid = $this->getJson($this->publicUrl('tickets/validate/MSA-TESTTICKET1'));
        $this->assertSuccessEnvelope($valid);
        $valid->assertJsonPath('data.valid', true);
        $valid->assertJsonPath('data.code', 'MSA-TESTTICKET1');
        $this->assertArrayNotHasKey('holder_email', $valid->json('data'));

        // Validation must not redeem the ticket.
        $this->assertSame(TicketStatus::Issued, $ticket->fresh()->status);

        $missing = $this->getJson($this->publicUrl('tickets/validate/MSA-DOESNOTEXIST'));
        $missing->assertStatus(404);

        $ticket->update(['status' => TicketStatus::Revoked]);
        $revoked = $this->getJson($this->publicUrl('tickets/validate/MSA-TESTTICKET1'));
        $revoked->assertStatus(409);

        $qr = $this->get($this->publicUrl('tickets/MSA-TESTTICKET1/qr'));
        $qr->assertOk();
        $qr->assertHeader('Content-Type', 'image/png');
    }

    public function test_draft_event_cannot_be_registered(): void
    {
        Event::factory()->create([
            'slug' => 'draft-reg',
            'is_public' => true,
            'status' => EventStatus::Draft,
        ]);

        $response = $this->postJson($this->publicUrl('events/draft-reg/register'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'ab@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_my_tickets_retrieves_user_registrations(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();
        
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => 'confirmed',
        ]);
        Ticket::factory()->forRegistration($registration)->create([
            'code' => 'MSA-MYTICKET1',
            'qr_payload' => 'MSA-MYTICKET1',
        ]);

        $response = $this->actingAsEms($user)->getJson($this->url('public/my-tickets'));

        $response->assertStatus(200);
        $this->assertSuccessEnvelope($response);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($registration->reference, $response->json('data.0.reference'));
        $this->assertSame('MSA-MYTICKET1', $response->json('data.0.tickets.0.code'));
    }

    public function test_user_can_cancel_their_registration(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();
        
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => 'confirmed',
            'quantity' => 1,
        ]);
        $ticket = Ticket::factory()->forRegistration($registration)->create([
            'code' => 'MSA-CANCELTKT',
            'qr_payload' => 'MSA-CANCELTKT',
            'status' => TicketStatus::Issued->value,
        ]);

        $response = $this->actingAsEms($user)->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertStatus(200);
        $this->assertSuccessEnvelope($response);
        $this->assertSame('cancelled', $response->json('data.status'));
        
        $this->assertSame(TicketStatus::Revoked, $ticket->fresh()->status);
        $this->assertDatabaseHas('ems_registrations', [
            'id' => $registration->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_my_tickets_includes_pending_checkout_for_unpaid_registrations(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();
        $ticketType = TicketType::factory()->paid(25)->create(['event_id' => $event->id]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'buyer_email' => $user->email,
            'total_amount' => 25,
        ]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 25,
            'confirmed_at' => null,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 25,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Pending->value,
            'checkout_url' => 'https://square.test/pay/saved',
            'checkout_version' => 2,
            'checkout_expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAsEms($user)->getJson($this->url('public/my-tickets'));

        $response->assertStatus(200);
        $this->assertSuccessEnvelope($response);
        $this->assertSame('awaiting_payment', $response->json('data.0.status'));
        $this->assertSame($ticketType->name, $response->json('data.0.ticket_type.name'));
        $this->assertSame($order->uuid, $response->json('data.0.pending_checkout.order_uuid'));
        $this->assertSame('https://square.test/pay/saved', $response->json('data.0.pending_checkout.checkout_url'));
        $this->assertEquals(25, $response->json('data.0.pending_checkout.amount'));
        $this->assertSame(2, $response->json('data.0.pending_checkout.checkout_version'));
    }

    public function test_user_can_cancel_registration_after_event_soft_deleted(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();
        $ticketType = TicketType::factory()->free()->create([
            'event_id' => $event->id,
            'quantity_sold' => 1,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => 'confirmed',
            'quantity' => 1,
        ]);
        $ticket = Ticket::factory()->forRegistration($registration)->create([
            'code' => 'MSA-SOFTDEL',
            'qr_payload' => 'MSA-SOFTDEL',
            'status' => TicketStatus::Issued->value,
        ]);

        $event->delete();
        $this->assertNull($registration->fresh()->event);

        $response = $this->actingAsEms($user)
            ->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertOk();
        $this->assertSame('cancelled', $response->json('data.status'));
        $this->assertSame(TicketStatus::Revoked, $ticket->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
    }

    public function test_my_tickets_pending_checkout_can_be_cancelled_via_checkout_cancel(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.environment' => 'sandbox',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*/v2/online-checkout/payment-links/*' => \Illuminate\Support\Facades\Http::response([], 200),
        ]);

        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();
        $ticketType = TicketType::factory()->paid(25)->create([
            'event_id' => $event->id,
            'quantity_sold' => 1,
        ]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'buyer_email' => $user->email,
            'buyer_name' => $user->name,
            'total_amount' => 25,
            'status' => \App\Ems\Enums\OrderStatus::Pending,
        ]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 25,
            'confirmed_at' => null,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 25,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Processing->value,
            'provider_checkout_id' => 'plink_my_tickets_cancel',
            'checkout_url' => 'https://square.test/pay/my-tickets',
            'checkout_version' => 1,
            'checkout_expires_at' => now()->addHours(2),
        ]);

        $tickets = $this->actingAsEms($user)->getJson($this->url('public/my-tickets'));
        $tickets->assertOk();
        $this->assertSame('awaiting_payment', $tickets->json('data.0.status'));
        $this->assertSame($order->uuid, $tickets->json('data.0.pending_checkout.order_uuid'));

        $cancel = $this->postJson($this->publicUrl("events/{$event->slug}/checkout/cancel"), [
            'email' => $user->email,
            'order_uuid' => $order->uuid,
        ]);
        $cancel->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, Payment::query()->first()->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $refreshed = $this->actingAsEms($user)->getJson($this->url('public/my-tickets'));
        $refreshed->assertOk();
        $this->assertSame('cancelled', $refreshed->json('data.0.status'));
        $this->assertNull($refreshed->json('data.0.pending_checkout'));
    }

    public function test_my_tickets_supports_active_only_filtering(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();

        $activeReg = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => RegistrationStatus::Confirmed->value,
            'quantity' => 1,
        ]);

        $refundedReg = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => RegistrationStatus::Refunded->value,
            'quantity' => 1,
        ]);

        // Default list returns both registrations
        $all = $this->actingAsEms($user)->getJson($this->url('public/my-tickets'));
        $all->assertOk();
        $this->assertCount(2, $all->json('data'));

        // Active only list filters out refunded registration
        $active = $this->actingAsEms($user)->getJson($this->url('public/my-tickets?active_only=1'));
        $active->assertOk();
        $this->assertCount(1, $active->json('data'));
        $this->assertSame($activeReg->reference, $active->json('data.0.reference'));
        $this->assertTrue($active->json('data.0.is_active'));
    }

    public function test_cancelling_refunded_registration_returns_409_conflict_instead_of_500_or_403(): void
    {
        $user = $this->emsUser(\App\Ems\Support\EmsRoles::ATTENDEE);
        $event = $this->publicEvent();

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendee_name' => $user->name,
            'attendee_email' => $user->email,
            'status' => RegistrationStatus::Refunded->value,
            'quantity' => 1,
        ]);

        $response = $this->actingAsEms($user)->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertStatus(409);
        $this->assertSame('This registration has already been refunded and cannot be cancelled.', $response->json('message'));
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
    }

    public function test_featured_filter_returns_only_featured_events(): void
    {
        $featured = $this->publicEvent(['name' => 'Featured Event', 'is_featured' => true]);
        $regular = $this->publicEvent(['name' => 'Regular Event', 'is_featured' => false]);

        $response = $this->getJson($this->publicUrl('events?featured=1'));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($featured->slug));
        $this->assertFalse($slugs->contains($regular->slug));
        $this->assertTrue($response->json('data.0.is_featured'));
    }
}
