<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\BadgeAwardFactory.php

namespace Database\Factories;

use App\Models\BadgeAward;
use App\Models\Badge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BadgeAwardFactory extends Factory
{
    protected $model = BadgeAward::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'badge_id' => Badge::factory(),
            'awarded_at' => now(),
        ];
    }
}
