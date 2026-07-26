<?php

namespace Database\Factories\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(3, true));
        $startAt = $this->faker->dateTimeBetween('+1 week', '+3 months');

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(2, true),
            'category_id' => null,
            'organizer_id' => null,
            'location' => $this->faker->streetAddress(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify('+2 hours'),
            'timezone' => 'America/Vancouver',
            'capacity' => $this->faker->numberBetween(20, 300),
            'status' => EventStatus::Draft,
            'is_public' => false,
        ];
    }

    /**
     * Place the event directly in a lifecycle state, stamping the timestamps
     * the lifecycle service would have written on the way there. Tests that
     * exercise transitions should still drive them through the service.
     */
    public function status(EventStatus $status): static
    {
        return $this->state(function () use ($status): array {
            $attributes = ['status' => $status];

            $reached = static fn (EventStatus ...$states): bool => in_array($status, $states, true);

            if ($reached(
                EventStatus::Published,
                EventStatus::RegistrationOpen,
                EventStatus::RegistrationClosed,
                EventStatus::Live,
                EventStatus::Completed,
                EventStatus::Archived
            )) {
                $attributes['published_at'] = now()->subDays(7);
            }

            if ($reached(
                EventStatus::RegistrationOpen,
                EventStatus::RegistrationClosed,
                EventStatus::Live,
                EventStatus::Completed,
                EventStatus::Archived
            )) {
                $attributes['registration_open_at'] = now()->subDays(6);
            }

            if ($reached(
                EventStatus::RegistrationClosed,
                EventStatus::Live,
                EventStatus::Completed,
                EventStatus::Archived
            )) {
                $attributes['registration_closed_at'] = now()->subDay();
            }

            if ($reached(EventStatus::Completed, EventStatus::Archived)) {
                $attributes['completed_at'] = now();
            }

            if ($reached(EventStatus::Archived)) {
                $attributes['archived_at'] = now();
            }

            return $attributes;
        });
    }

    public function organizedBy(User $user): static
    {
        return $this->state(fn (): array => [
            'organizer_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function publiclyDiscoverable(): static
    {
        return $this->state(fn (): array => [
            'is_public' => true,
        ])->status(EventStatus::RegistrationOpen);
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'start_at' => now()->subWeeks(2),
            'end_at' => now()->subWeeks(2)->addHours(2),
        ]);
    }
}
