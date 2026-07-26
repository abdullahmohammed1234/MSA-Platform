<?php

namespace Database\Factories\Ems;

use App\Ems\Enums\OrderStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'reference' => 'ORD-' . strtoupper(Str::random(8)),
            'event_id' => Event::factory(),
            'user_id' => null,
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->safeEmail(),
            'buyer_phone' => null,
            'total_amount' => 0,
            'currency' => 'CAD',
            'status' => OrderStatus::Pending,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
