<?php

namespace Tests\Feature\Academy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_academy_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/academy/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'stats' => ['coursesEnrolled', 'coursesCompleted', 'badgesUnlocked', 'overallProgress', 'totalXp', 'streakDays'],
                'courseProgress',
                'recentActivities',
                'recommendedCourses',
            ]);
    }

    public function test_unauthenticated_dashboard_request_is_rejected(): void
    {
        $this->getJson('/api/v1/academy/dashboard')->assertStatus(401);
    }
}
