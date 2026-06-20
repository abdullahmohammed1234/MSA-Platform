<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\CourseFactory.php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);
        return [
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'description' => $this->faker->paragraph,
            'thumbnail' => $this->faker->imageUrl(),
            'difficulty' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'estimated_duration' => $this->faker->numberBetween(30, 300),
            'status' => 'draft',
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
