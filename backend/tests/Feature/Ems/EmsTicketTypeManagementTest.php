<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Http;

class EmsTicketTypeManagementTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'queue.default' => 'sync',
        ]);
    }

    private function openEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->publiclyDiscoverable()->create(array_merge([
            'category_id' => $category->id,
            'capacity' => 100,
            'status' => EventStatus::RegistrationOpen,
        ], $attributes));
    }

    private function fakeSquareCatalog(): void
    {
        Http::fake([
            '*/v2/catalog/list*' => Http::response(['objects' => []], 200),
            '*/v2/catalog/object/*' => Http::response([
                'object' => ['id' => 'VAR_TEST', 'version' => 1, 'type' => 'ITEM_VARIATION', 'item_variation_data' => ['item_id' => 'ITEM_TEST']],
            ], 200),
            '*/v2/catalog/batch-upsert' => Http::response([
                'objects' => [[
                    'type' => 'ITEM',
                    'id' => 'ITEM_TEST',
                    'version' => 1,
                    'item_data' => ['variations' => [['type' => 'ITEM_VARIATION', 'id' => 'VAR_TEST', 'version' => 1]]],
                ]],
            ], 200),
        ]);
    }

    private function adminUser()
    {
        return $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
    }

    public function test_can_add_ticket_type_to_published_event_with_open_registration(): void
    {
        $event = $this->openEvent(['name' => 'Published Event']);
        $user = $this->adminUser();

        $this->fakeSquareCatalog();

        $response = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'General Admission',
            'price' => 25,
            'currency' => 'CAD',
            'quantity' => 100,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('ems_ticket_types', [
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 25.00,
            'quantity' => 100,
        ]);
    }

    public function test_creating_duplicate_name_on_active_event_returns_422(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        TicketType::factory()->create(['event_id' => $event->id, 'name' => 'General Admission']);

        $response = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'General Admission',
            'price' => 30,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_recreating_soft_deleted_ticket_type_name_restores_and_updates_without_500(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'VIP Pass', 'price' => 50]);
        $ticket->delete();

        $this->assertSoftDeleted('ems_ticket_types', ['id' => $ticket->id]);

        $response = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'VIP Pass',
            'price' => 75,
            'quantity' => 20,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('ems_ticket_types', [
            'id' => $ticket->id,
            'name' => 'VIP Pass',
            'price' => 75.00,
            'quantity' => 20,
            'deleted_at' => null,
        ]);
    }

    public function test_can_edit_safe_fields_on_ticket_type_with_sales(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id, 'name' => 'Original Name']);
        $ticketType->quantity_sold = 2;
        $ticketType->save();

        $response = $this->actingAsEms($user)->putJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}"),
            [
                'name' => 'Updated Tier Name',
                'description' => 'New Description',
                'price' => 15, // Price unchanged
                'max_per_order' => 5,
            ]
        );

        $response->assertOk();
        $this->assertSame('Updated Tier Name', $ticketType->fresh()->name);
        $this->assertSame('New Description', $ticketType->fresh()->description);
        $this->assertSame(5, $ticketType->fresh()->max_per_order);
    }

    public function test_editing_price_on_ticket_type_with_sales_returns_422(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id, 'name' => 'GA']);
        $ticketType->quantity_sold = 1;
        $ticketType->save();

        $response = $this->actingAsEms($user)->putJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}"),
            [
                'name' => 'GA',
                'price' => 30, // Unsafe price edit
            ]
        );

        $response->assertStatus(422);
        $this->assertSame(
            'Price cannot be changed after registrations or sales have occurred. Create a new ticket type or deactivate this one instead.',
            $response->json('message')
        );
        $this->assertSame(15.0, (float) $ticketType->fresh()->price);
    }

    public function test_can_delete_unused_ticket_type(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAsEms($user)->deleteJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}")
        );

        $response->assertOk();
        $this->assertSoftDeleted('ems_ticket_types', ['id' => $ticketType->id]);
    }

    public function test_deleting_ticket_type_with_sales_returns_409_conflict(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $response = $this->actingAsEms($user)->deleteJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}")
        );

        $response->assertStatus(409);
        $this->assertSame(
            'This ticket type has sales or registrations and cannot be deleted. Disable it instead.',
            $response->json('message')
        );
    }

    public function test_deactivated_ticket_type_cannot_be_purchased_by_public(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'is_active' => true]);

        // Disable ticket type
        $disable = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}/disable")
        );
        $disable->assertOk();
        $this->assertFalse((bool) $ticketType->fresh()->is_active);

        // Attempt public purchase/registration
        $checkout = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $checkout->assertStatus(409);
    }

    public function test_deactivated_ticket_type_preserves_existing_registrations_and_tickets(): void
    {
        $event = $this->openEvent();
        $user = $this->adminUser();
        $this->fakeSquareCatalog();

        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'is_active' => true]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);
        $ticket = Ticket::factory()->forRegistration($registration)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/tickets/{$ticketType->uuid}/disable")
        )->assertOk();

        $this->assertSame($ticketType->id, $registration->fresh()->ticket_type_id);
        $this->assertSame($ticketType->id, $ticket->fresh()->ticket_type_id);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    private function publicUrl(string $path): string
    {
        return '/' . trim((string) config('ems.route.prefix', 'api/v1/ems'), '/') . '/public/' . ltrim($path, '/');
    }
}
