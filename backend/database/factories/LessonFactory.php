<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\LessonFactory.php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        return [
            'module_id' => Module::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'content' => $this->faker->paragraphs(3, true),
            'video_url' => 'https://www.youtube.com/watch?v=' . Str::random(11),
            'attachments' => json_encode([]),
            'order' => $this->faker->numberBetween(1, 10),
            'estimated_duration' => $this->faker->numberBetween(10, 45),
            'is_required' => true,
        ];
    }
}
