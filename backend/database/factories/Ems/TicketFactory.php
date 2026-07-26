<?php

namespace Database\Factories\Ems;

use App\Ems\Enums\TicketStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'MSA-' . strtoupper(Str::random(10));

        return [
            'uuid' => (string) Str::uuid(),
            'code' => $code,
            'event_id' => Event::factory(),
            'registration_id' => Registration::factory(),
            'ticket_type_id' => null,
            'qr_payload' => $code,
            'qr_generated_at' => now(),
            'status' => TicketStatus::Issued,
            'holder_name' => $this->faker->name(),
            'holder_email' => $this->faker->safeEmail(),
            'issued_at' => now(),
        ];
    }

    public function forRegistration(Registration $registration): static
    {
        return $this->state(fn (): array => [
            'event_id' => $registration->event_id,
            'registration_id' => $registration->id,
            'holder_name' => $registration->attendee_name,
            'holder_email' => $registration->attendee_email,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
