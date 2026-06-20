<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\AnalyticsEventFactory.php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'session_id' => AnalyticsSession::factory(),
            'module' => 'academy',
            'event_type' => 'click',
            'event_name' => 'course_click',
            'entity_type' => 'App\Models\Course',
            'entity_id' => 1,
            'metadata' => ['course_title' => 'Intro to Dawah'],
            'occurred_at' => now(),
        ];
    }
}
