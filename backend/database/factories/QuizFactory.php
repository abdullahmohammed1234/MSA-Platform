<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\QuizFactory.php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(3) . ' Quiz',
            'description' => $this->faker->paragraph,
            'passing_score' => 70,
            'time_limit' => $this->faker->randomElement([null, 15, 30, 60]),
            'attempt_limit' => $this->faker->randomElement([null, 1, 2, 3]),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }
}
