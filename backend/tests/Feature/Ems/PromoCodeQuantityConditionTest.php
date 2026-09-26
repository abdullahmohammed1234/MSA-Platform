<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\PromoCode;
use App\Ems\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromoCodeQuantityConditionTest extends EmsTestCase
{
    use RefreshDatabase;

    /** @test */
    public function promo_code_validates_min_and_max_ticket_quantity_conditions(): void
    {
        $category = $this->category();
        $event = Event::create([
            'category_id' => $category->id,
            'name' => 'Quantity Promo Event',
            'slug' => 'quantity-promo-event-' . uniqid(),
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'America/Vancouver',
            'status' => 'published',
            'is_published' => true,
            'capacity' => 100,
        ]);

        $promo = PromoCode::create([
            'code' => 'BUY2PAIR',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_quantity' => 2,
            'max_quantity' => 2,
            'is_active' => true,
        ]);

        $res1 = $promo->isValidFor($event, null, null, 100.0, null, 1);
        $this::assertFalse($res1['valid'], 'buying 1 ticket fails min_quantity & max_quantity of 2');

        $res2 = $promo->isValidFor($event, null, null, 100.0, null, 2);
        $this::assertTrue($res2['valid'], 'buying 2 tickets passes exact quantity condition of 2');

        $res3 = $promo->isValidFor($event, null, null, 100.0, null, 3);
        $this::assertFalse($res3['valid'], 'buying 3 tickets fails max_quantity of 2');
    }

    /** @test */
    public function promo_code_validation_endpoint_respects_quantity_param(): void
    {
        $category = $this->category();
        $event = Event::create([
            'category_id' => $category->id,
            'name' => 'Quantity Promo Event 2',
            'slug' => 'quantity-promo-event-2-' . uniqid(),
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'America/Vancouver',
            'status' => 'published',
            'is_published' => true,
            'capacity' => 100,
        ]);

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 25.00,
            'capacity' => 50,
            'quantity_sold' => 0,
            'status' => 'active',
        ]);

        $promo = PromoCode::create([
            'code' => 'DUO2026',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'min_quantity' => 2,
            'max_quantity' => 2,
            'is_active' => true,
        ]);

        // Validate with 1 ticket -> Should fail
        $response1 = $this->postJson("/api/v1/ems/public/promo-codes/validate", [
            'code' => 'DUO2026',
            'event_uuid' => $event->uuid,
            'ticket_type_uuid' => $ticketType->uuid,
            'amount' => 50.00,
            'quantity' => 1,
        ]);

        $response1->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This promo code is only valid when purchasing exactly 2 ticket(s).');

        // Validate with 2 tickets -> Should succeed
        $response2 = $this->postJson("/api/v1/ems/public/promo-codes/validate", [
            'code' => 'DUO2026',
            'event_uuid' => $event->uuid,
            'ticket_type_uuid' => $ticketType->uuid,
            'amount' => 50.00,
            'quantity' => 2,
        ]);

        $response2->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.discount_amount', 25); // 50% off $50 total
    }
}

