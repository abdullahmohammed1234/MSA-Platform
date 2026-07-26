<?php

namespace Database\Factories\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'event_id' => Event::factory(),
            'name' => 'General Admission',
            'description' => null,
            'price' => 0,
            'currency' => 'CAD',
            'quantity' => null,
            'quantity_sold' => 0,
            'sales_start_at' => null,
            'sales_end_at' => null,
            'is_active' => true,
            'is_visible' => true,
            'max_per_order' => null,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0, 'name' => 'Free Admission']);
    }

    public function paid(float $price = 25.00): static
    {
        return $this->state(fn () => [
            'price' => $price,
            'name' => 'General Admission',
        ]);
    }

    public function limited(int $quantity): static
    {
        return $this->state(fn () => ['quantity' => $quantity]);
    }
}
