<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\ModuleFactory.php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'order' => $this->faker->numberBetween(1, 10),
            'estimated_duration' => $this->faker->numberBetween(15, 60),
        ];
    }
}
