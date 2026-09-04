<?php

namespace Database\Factories;

use App\Enums\VolunteerRegistrationStatus;
use App\Models\VolunteerRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VolunteerRegistrationFactory extends Factory
{
    protected $model = VolunteerRegistration::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->userName() . '@sfu.ca',
            'student_number' => (string) fake()->numberBetween(301000000, 301999999),
            'department' => fake()->randomElement([
                'Education',
                'Finance',
                'Events',
                'Marketing (Media & Comms)',
                'IT',
                'General Inquiries',
            ]),
            'interests' => fake()->paragraph(),
            'experience' => fake()->optional()->sentence(),
            'status' => VolunteerRegistrationStatus::New,
            'admin_notes' => null,
            'assigned_to' => null,
            'contacted_at' => null,
            'processed_at' => null,
        ];
    }
}
