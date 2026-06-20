<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\AnalyticsSessionFactory.php

namespace Database\Factories;

use App\Models\AnalyticsSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalyticsSessionFactory extends Factory
{
    protected $model = AnalyticsSession::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'started_at' => now(),
            'ended_at' => now()->addMinutes(10),
            'duration' => 600,
            'device' => 'Desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'referrer' => 'https://google.com',
        ];
    }
}
