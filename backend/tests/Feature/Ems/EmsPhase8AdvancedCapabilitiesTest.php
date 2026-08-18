<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Models\EventFeedback;
use App\Ems\Models\EventSeries;
use App\Ems\Models\EventTemplate;
use App\Ems\Models\Order;
use App\Ems\Models\PromoCode;
use App\Ems\Models\Registration;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmsPhase8AdvancedCapabilitiesTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.webhook_signature_key' => 'webhook-secret',
            'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
            'ems.payments.square.environment' => 'sandbox',
            'queue.default' => 'sync',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*/v2/catalog/*' => \Illuminate\Support\Facades\Http::response(['objects' => [], 'id_mappings' => []], 200),
            '*/v2/online-checkout/payment-links' => \Illuminate\Support\Facades\Http::response([
                'payment_link' => [
                    'id' => 'plink_vip',
                    'url' => 'https://square.test/checkout/vip',
                    'order_id' => 'sq_order_vip',
                ],
            ], 200),
        ]);
    }
    /** @test */
    public function event_templates_crud_actions(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        
        // Grant permissions to the organizer for templates
        $role = $admin->roles->first();
        $role->permissions()->syncWithoutDetaching([
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::EVENT_TEMPLATES_VIEW], ['name' => 'EMS: View Event Templates', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::EVENT_TEMPLATES_MANAGE], ['name' => 'EMS: Manage Event Templates', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
        ]);

        $this->actingAsEms($admin);

        $category = $this->category();

        // 1. Create template
        $response = $this->postJson($this->url('event-templates'), [
            'name' => 'Test Template',
            'description' => 'A test template description',
            'category_id' => $category->id,
            'capacity' => 120,
            'is_public' => true,
            'waitlist_enabled' => true,
            'max_tickets_per_order' => 5,
            'max_registrations_per_attendee' => 1,
            'settings' => ['ticket_types' => [['name' => 'General', 'price' => 0.0, 'quantity' => 120]]],
            'is_default' => false,
        ]);

        $this->assertSuccessEnvelope($response);
        $templateUuid = $response->json('data.uuid');
        $this->assertNotNull($templateUuid);

        // 2. View templates index
        $response = $this->getJson($this->url('event-templates'));
        $this->assertSuccessEnvelope($response);
        $this->assertCount(1, $response->json('data'));

        // 3. View template details
        $response = $this->getJson($this->url("event-templates/{$templateUuid}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.capacity', 120);

        // 4. Update template
        $response = $this->putJson($this->url("event-templates/{$templateUuid}"), [
            'name' => 'Test Template Updated',
            'description' => 'A test template description',
            'category_id' => $category->id,
            'capacity' => 150,
            'is_public' => true,
            'waitlist_enabled' => true,
            'max_tickets_per_order' => 5,
            'max_registrations_per_attendee' => 1,
            'settings' => ['ticket_types' => [['name' => 'General', 'price' => 0.0, 'quantity' => 150]]],
            'is_default' => true,
        ]);
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.capacity', 150);

        // 5. Duplicate template
        $response = $this->postJson($this->url("event-templates/{$templateUuid}/duplicate"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.name', 'Test Template Updated (Copy)');

        // 6. Delete template
        $response = $this->deleteJson($this->url("event-templates/{$templateUuid}"));
        $this->assertSuccessEnvelope($response);

        // 7. Verify deletion (archived_at set)
        $response = $this->getJson($this->url('event-templates'));
        // The deleted template should not show in the index list since it's archived
        $this->assertCount(1, $response->json('data')); // Only the copy remains
    }

    /** @test */
    public function event_series_recurring_generation_and_updates(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        
        $role = $admin->roles->first();
        $role->permissions()->syncWithoutDetaching([
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::SERIES_VIEW], ['name' => 'EMS: View Event Series', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::SERIES_MANAGE], ['name' => 'EMS: Manage Event Series', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
        ]);

        $this->actingAsEms($admin);

        $category = $this->category();

        // 1. Create a weekly series of Halaqah on Mondays and Fridays
        $startDate = Carbon::today()->addWeek()->startOfWeek(); // next Monday
        $endDate = (clone $startDate)->addDays(11); // covers 2 full weeks (next Mon to the Mon+11 days which is Friday week 2)

        $response = $this->postJson($this->url('event-series'), [
            'name' => 'Weekly Halaqah',
            'description' => 'A weekly religious study circle',
            'recurrence_pattern' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_days' => ['Monday', 'Friday'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category_id' => $category->id,
            'location' => 'MSA Hall',
            'timezone' => 'America/Vancouver',
            'capacity' => 50,
            'waitlist_enabled' => true,
            'max_tickets_per_order' => 2,
            'max_registrations_per_attendee' => 1,
            'is_public' => true,
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $this->assertSuccessEnvelope($response);
        $seriesUuid = $response->json('data.uuid');
        $this->assertNotNull($seriesUuid);
        
        // Let's verify we generated exactly 4 events (Mon/Fri of Week 1, Mon/Fri of Week 2)
        $this->assertCount(4, $response->json('data.events'));

        // 2. Update entire series details (propagate to all events)
        $response = $this->putJson($this->url("event-series/{$seriesUuid}"), [
            'name' => 'Weekly Halaqah Updated',
            'description' => 'A weekly study circle updated description',
            'category_id' => $category->id,
            'location' => 'Main Mosque Hall',
            'timezone' => 'America/Vancouver',
            'capacity' => 60,
            'waitlist_enabled' => true,
            'max_tickets_per_order' => 2,
            'max_registrations_per_attendee' => 1,
            'is_public' => true,
        ]);
        $this->assertSuccessEnvelope($response);
        $this->assertEquals('Main Mosque Hall', $response->json('data.events.0.location'));
        $this->assertEquals(60, $response->json('data.events.0.capacity'));

        // 3. Update future occurrences starting from the second event
        $events = Event::orderBy('start_at', 'asc')->get();
        $secondEvent = $events[1];
        
        $response = $this->putJson($this->url("event-series/{$seriesUuid}/events/{$secondEvent->uuid}"), [
            'location' => 'Zoom Online',
            'capacity' => 100,
            'waitlist_enabled' => false,
            'is_public' => true,
        ]);
        $this->assertSuccessEnvelope($response);
        
        // First event should NOT be modified (still Main Mosque Hall)
        $this->assertEquals('Main Mosque Hall', $events[0]->fresh()->location);
        
        // Second, third, fourth should be Zoom Online
        $this->assertEquals('Zoom Online', $events[1]->fresh()->location);
        $this->assertEquals('Zoom Online', $events[2]->fresh()->location);
        $this->assertEquals('Zoom Online', $events[3]->fresh()->location);

        // 4. Cancel the entire series
        $response = $this->deleteJson($this->url("event-series/{$seriesUuid}"));
        $this->assertSuccessEnvelope($response);
        
        foreach (Event::all() as $e) {
            $this->assertEquals('cancelled', $e->status->value);
        }
    }

    /** @test */
    public function promo_codes_checkout_discounts(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        
        $role = $admin->roles->first();
        $role->permissions()->syncWithoutDetaching([
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::PROMO_CODES_VIEW], ['name' => 'EMS: View Promo Codes', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::PROMO_CODES_MANAGE], ['name' => 'EMS: Manage Promo Codes', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
        ]);

        $this->actingAsEms($admin);

        // 1. Create a percentage promo code
        $response = $this->postJson($this->url('promo-codes'), [
            'code' => 'FIFTYOFF',
            'description' => '50% discount on tickets',
            'discount_type' => 'percentage',
            'discount_value' => 50.0,
            'usage_limit' => 100,
            'usage_per_attendee' => 1,
            'start_date' => Carbon::yesterday()->toDateString(),
            'end_date' => Carbon::tomorrow()->toDateString(),
            'minimum_purchase' => 10.0,
            'is_active' => true,
        ]);
        $this->assertSuccessEnvelope($response);
        $promoUuid = $response->json('data.uuid');

        // Create an event with paid ticket
        $event = $this->event([
            'is_public' => true,
            'status' => \App\Ems\Enums\EventStatus::RegistrationOpen->value,
            'start_at' => Carbon::tomorrow()->addHours(2),
            'registration_deadline_at' => Carbon::tomorrow(),
        ]);
        $ticketType = \App\Ems\Models\TicketType::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'name' => 'Paid Entry',
            'price' => 20.0,
            'currency' => 'CAD',
            'quantity' => 100,
            'quantity_sold' => 0,
            'sales_start_at' => Carbon::yesterday(),
            'sales_end_at' => Carbon::tomorrow(),
        ]);

        $response = $this->postJson($this->url('promo-codes/validate'), [
            'code' => 'FIFTYOFF',
            'event_uuid' => $event->uuid,
            'ticket_type_uuid' => $ticketType->uuid,
            'amount' => 20.0,
        ]);
        $this->assertSuccessEnvelope($response);
        $this->assertEquals(10.0, $response->json('data.discount_amount'));

        // 3. Checkout with promo code making price $10.00
        $this->forgetAuthentication();
        $this->defaultHeaders = [];
        $response = $this->postJson("/api/v1/ems/public/events/{$event->slug}/checkout", [
            'first_name' => 'Bilal',
            'last_name' => 'Anwar',
            'email' => 'bilal@msa.org',
            'phone' => '7781234567',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
            'promo_code' => 'FIFTYOFF',
        ]);
        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.checkout_url')); // Requires payment integration redirect since amount_due > 0
        $this->assertEquals(10.0, $response->json('data.order.total_amount'));
        $regBilal = Registration::where('attendee_email', 'bilal@msa.org')->first();
        $this->assertEquals(10.0, $regBilal->amount_due);

        // Assert that the Square payment link request payload included the discount
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/v2/online-checkout/payment-links')) {
                $orderPayload = $request['order'] ?? [];
                $discounts = $orderPayload['discounts'] ?? [];
                return count($discounts) === 1 
                    && $discounts[0]['name'] === 'FIFTYOFF' 
                    && $discounts[0]['type'] === 'FIXED_AMOUNT'
                    && $discounts[0]['amount_money']['amount'] === 1000
                    && $discounts[0]['scope'] === 'ORDER';
            }
            return false;
        });

        // 4. Create a 100% discount promo code
        $this->actingAsEms($admin);
        $response = $this->postJson($this->url('promo-codes'), [
            'code' => 'FREESTUFF',
            'discount_type' => 'free',
            'discount_value' => 100.0,
            'usage_per_attendee' => 1,
            'is_active' => true,
        ]);
        $this->assertSuccessEnvelope($response);

        // 5. Checkout with 100% discount (should bypass Square and complete immediately)
        $this->forgetAuthentication();
        $this->defaultHeaders = [];
        $response = $this->postJson("/api/v1/ems/public/events/{$event->slug}/checkout", [
            'first_name' => 'Imran',
            'last_name' => 'Khan',
            'email' => 'imran@msa.org',
            'phone' => '7781234568',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
            'promo_code' => 'FREESTUFF',
        ]);
        $response->assertStatus(201);
        $this->assertNull($response->json('data.checkout_url')); // Completed immediately!
        $this->assertEquals(0.0, $response->json('data.order.total_amount'));
        $this->assertEquals('confirmed', $response->json('data.registration.status'));
        $regImran = Registration::where('attendee_email', 'imran@msa.org')->first();
        $this->assertEquals(0.0, $regImran->amount_due);
    }

    /** @test */
    public function feedback_submission_and_reporting(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        
        $role = $admin->roles->first();
        $role->permissions()->syncWithoutDetaching([
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::FEEDBACK_VIEW], ['name' => 'EMS: View Feedback', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::ANALYTICS_VIEW], ['name' => 'EMS: View Analytics', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
        ]);

        $this->actingAsEms($admin);

        $event = $this->event(['status' => 'published']);
        
        // Create confirmed attendee
        $attendeeUser = $this->emsUser();
        $reg = Registration::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $attendeeUser->id,
            'reference' => 'REG-12345',
            'attendee_name' => $attendeeUser->name,
            'attendee_email' => $attendeeUser->email,
            'status' => 'confirmed',
            'type' => 'free',
            'quantity' => 1,
            'registered_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Submit feedback as the attendee
        $this->forgetAuthentication();
        $this->actingAsEms($attendeeUser);

        $response = $this->postJson($this->url("events/{$event->uuid}/feedback"), [
            'overall_rating' => 5,
            'organization_rating' => 4,
            'program_rating' => 5,
            'venue_rating' => 4,
            'text_feedback' => 'Incredible halaqah!',
            'is_anonymous' => false,
        ]);
        $this->assertSuccessEnvelope($response);

        // Try to submit duplicate feedback (should fail)
        $response = $this->postJson($this->url("events/{$event->uuid}/feedback"), [
            'overall_rating' => 3,
            'organization_rating' => 3,
            'program_rating' => 3,
            'venue_rating' => 3,
        ]);
        $response->assertStatus(409); // Conflict / Already submitted

        // View feedback analytics as the organizer
        $this->forgetAuthentication();
        $this->actingAsEms($admin);

        $response = $this->getJson($this->url("events/{$event->uuid}/feedback"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.total_responses', 1);
        $response->assertJsonPath('data.average_overall_rating', 5);
        $response->assertJsonPath('data.comments.0.text_feedback', 'Incredible halaqah!');
    }

    /** @test */
    public function calendar_links_and_ics_downloads(): void
    {
        $event = $this->event([
            'name' => 'Annual Welcome Social',
            'slug' => 'annual-welcome-social',
            'description' => 'Welcome dinner event description',
            'location' => 'Main Campus Hall',
            'start_at' => Carbon::parse('2026-09-01 18:00:00'),
            'end_at' => Carbon::parse('2026-09-01 21:00:00'),
        ]);

        // 1. Get compose links
        $response = $this->getJson("/api/v1/ems/public/events/{$event->slug}/calendar");
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['google', 'outlook', 'yahoo', 'ics']]);
        $this->assertStringContainsString('Annual+Welcome+Social', $response->json('data.google'));

        // 2. Download dynamic .ics file
        $response = $this->get("/api/v1/ems/public/events/{$event->slug}/ics");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('SUMMARY:Annual Welcome Social', $response->getContent());
        $this->assertStringContainsString('LOCATION:Main Campus Hall', $response->getContent());
    }

    /** @test */
    public function advanced_analytics_funnel_reporting(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        
        $role = $admin->roles->first();
        $role->permissions()->syncWithoutDetaching([
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::ANALYTICS_VIEW], ['name' => 'EMS: View Analytics', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
            \App\Models\Permission::firstOrCreate(['slug' => EmsPermissions::ANALYTICS_VIEW_FINANCIAL], ['name' => 'EMS: View Financial Analytics', 'uuid' => (string) Str::uuid(), 'module' => EmsPermissions::MODULE])->id,
        ]);

        $this->actingAsEms($admin);

        $event = $this->event([
            'views_count' => 10,
            'registrations_started_count' => 5,
            'organizer_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        // Create confirmed attendee
        $reg = Registration::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'reference' => 'REG-999',
            'attendee_name' => 'Zayd',
            'attendee_email' => 'zayd@msa.org',
            'status' => 'confirmed',
            'type' => 'free',
            'quantity' => 2,
            'registered_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Issue tickets
        $ticket = \App\Ems\Models\Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'ticket_type_id' => null,
            'code' => 'TCK-111',
            'status' => 'issued',
        ]);

        // Check in
        \App\Ems\Models\CheckIn::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'registration_id' => $reg->id,
            'checked_in_at' => now(),
        ]);

        // Retrieve advanced report
        $response = $this->getJson($this->url('analytics/advanced-report') . "?event_uuid={$event->uuid}");
        $this->assertSuccessEnvelope($response);

        // Verify Conversion Funnel numbers
        $response->assertJsonPath('data.funnel.views', 10);
        $response->assertJsonPath('data.funnel.started', 5);
        $response->assertJsonPath('data.funnel.completed', 2);
        $response->assertJsonPath('data.funnel.tickets_issued', 1);
        $response->assertJsonPath('data.funnel.checked_in', 1);
        
        // Verify rates
        $this->assertEquals(50.0, $response->json('data.funnel.rates.views_to_started'));
        $this->assertEquals(40.0, $response->json('data.funnel.rates.started_to_completed'));
    }
}
