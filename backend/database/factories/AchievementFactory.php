<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\AchievementFactory.php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        $title = $this->faker->words(3, true) . ' Achievement';
        return [
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'description' => $this->faker->sentence(),
            'type' => 'completion',
            'points' => 50,
            'criteria_type' => 'course_completed',
            'criteria_value' => ['course_id' => 1],
        ];
    }
}
