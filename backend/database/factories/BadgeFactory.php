<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\BadgeFactory.php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true) . ' Badge';
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'description' => $this->faker->sentence(),
            'image_path' => 'badges/images/badge.png',
            'criteria_type' => 'lessons_count',
            'criteria_value' => ['count' => 5],
        ];
    }
}
