<?php

namespace Database\Seeders;

use App\Volunteering\Models\Achievement;
use Illuminate\Database\Seeder;

class Vms8AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Contribution',
                'slug' => 'first-contribution',
                'description' => 'Completed your first MSA volunteering opportunity.',
                'category' => 'participation',
                'icon' => 'star',
                'rule_type' => 'completed_opportunities',
                'criteria_config' => ['threshold' => 1],
                'points' => 10,
                'sort_order' => 1,
            ],
            [
                'name' => 'Dedicated Volunteer',
                'slug' => 'dedicated-volunteer',
                'description' => 'Completed 5 volunteering opportunities.',
                'category' => 'participation',
                'icon' => 'award',
                'rule_type' => 'completed_opportunities',
                'criteria_config' => ['threshold' => 5],
                'points' => 25,
                'sort_order' => 2,
            ],
            [
                'name' => 'Community Champion',
                'slug' => 'community-champion',
                'description' => 'Completed 10 volunteering opportunities.',
                'category' => 'participation',
                'icon' => 'trophy',
                'rule_type' => 'completed_opportunities',
                'criteria_config' => ['threshold' => 10],
                'points' => 50,
                'sort_order' => 3,
            ],
            [
                'name' => '5 Verified Hours',
                'slug' => 'hours-5',
                'description' => 'Contributed 5 verified service hours.',
                'category' => 'service_hours',
                'icon' => 'clock',
                'rule_type' => 'verified_service_hours',
                'criteria_config' => ['threshold' => 5.0],
                'points' => 15,
                'sort_order' => 4,
            ],
            [
                'name' => '10 Verified Hours',
                'slug' => 'hours-10',
                'description' => 'Contributed 10 verified service hours.',
                'category' => 'service_hours',
                'icon' => 'clock',
                'rule_type' => 'verified_service_hours',
                'criteria_config' => ['threshold' => 10.0],
                'points' => 30,
                'sort_order' => 5,
            ],
            [
                'name' => '25 Verified Hours',
                'slug' => 'hours-25',
                'description' => 'Contributed 25 verified service hours.',
                'category' => 'service_hours',
                'icon' => 'clock',
                'rule_type' => 'verified_service_hours',
                'criteria_config' => ['threshold' => 25.0],
                'points' => 60,
                'sort_order' => 6,
            ],
            [
                'name' => '50 Hours Milestone',
                'slug' => 'hours-50',
                'description' => 'Reached the 50 verified service hours milestone.',
                'category' => 'milestones',
                'icon' => 'milestone',
                'rule_type' => 'verified_service_hours',
                'criteria_config' => ['threshold' => 50.0],
                'points' => 100,
                'sort_order' => 7,
            ],
            [
                'name' => '100 Hours Century Club',
                'slug' => 'hours-100',
                'description' => 'Contributed 100 verified service hours to the community.',
                'category' => 'milestones',
                'icon' => 'crown',
                'rule_type' => 'verified_service_hours',
                'criteria_config' => ['threshold' => 100.0],
                'points' => 200,
                'sort_order' => 8,
            ],
            [
                'name' => 'Profile Completed',
                'slug' => 'profile-completed',
                'description' => 'Completed 80% or more of your volunteer profile details.',
                'category' => 'consistency',
                'icon' => 'user-check',
                'rule_type' => 'profile_completion',
                'criteria_config' => ['threshold' => 80],
                'points' => 20,
                'sort_order' => 9,
            ],
        ];

        foreach ($achievements as $data) {
            Achievement::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
