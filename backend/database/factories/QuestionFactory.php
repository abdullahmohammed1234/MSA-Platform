<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\QuestionFactory.php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'type' => 'multiple_choice',
            'category' => 'General',
            'difficulty' => 'easy',
            'tags' => ['general'],
            'question' => $this->faker->sentence(10) . '?',
            'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_answer' => ['Option A'],
            'points' => 1,
            'order' => 1,
        ];
    }
}
