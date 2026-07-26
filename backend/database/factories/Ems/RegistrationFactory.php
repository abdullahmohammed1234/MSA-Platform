<?php

namespace Database\Factories\Ems;

use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = $this->faker->firstName();
        $last = $this->faker->lastName();

        return [
            'uuid' => (string) Str::uuid(),
            'reference' => 'REG-' . strtoupper(Str::random(6)),
            'event_id' => Event::factory(),
            'user_id' => null,
            'ticket_type_id' => null,
            'attendee_name' => $first . ' ' . $last,
            'attendee_email' => $this->faker->unique()->safeEmail(),
            'attendee_phone' => null,
            'status' => RegistrationStatus::Confirmed,
            'type' => RegistrationType::Free,
            'quantity' => 1,
            'amount_due' => 0,
            'currency' => 'CAD',
            'registered_at' => now(),
            'confirmed_at' => now(),
            'notes' => null,
            'metadata' => [
                'first_name' => $first,
                'last_name' => $last,
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Pending,
            'confirmed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
