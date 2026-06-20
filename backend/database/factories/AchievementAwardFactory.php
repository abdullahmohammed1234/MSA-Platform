<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\AchievementAwardFactory.php

namespace Database\Factories;

use App\Models\AchievementAward;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AchievementAwardFactory extends Factory
{
    protected $model = AchievementAward::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'achievement_id' => Achievement::factory(),
            'unlocked_at' => now(),
        ];
    }
}
