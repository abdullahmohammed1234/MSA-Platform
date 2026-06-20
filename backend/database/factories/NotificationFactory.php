<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\NotificationFactory.php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'type' => 'App\Notifications\GenericNotification',
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->sentence(10),
            'data' => ['link' => '/dashboard'],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}
