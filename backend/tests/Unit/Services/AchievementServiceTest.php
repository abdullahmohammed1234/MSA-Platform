<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\AchievementServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Services\AchievementService;
use App\Services\MilestoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AchievementService $achievementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MilestoneService::class, $this->createMock(MilestoneService::class));
        $this->achievementService = new AchievementService();
    }

    public function test_get_achievements_for_user()
    {
        $user = User::factory()->create();
        $achievement = Achievement::factory()->create();
        AchievementAward::factory()->create([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        $results = $this->achievementService->getAchievementsForUser($user->id);

        $this->assertCount(1, $results);
        $this->assertNotEmpty($results->first()->awards);
    }

    public function test_evaluate_achievements_awards_on_criteria_match()
    {
        Notification::fake();

        $user = User::factory()->create();
        $achievement = Achievement::factory()->create([
            'criteria_type' => 'course_completed',
            'criteria_value' => ['course_id' => 5],
        ]);

        $this->achievementService->evaluateAchievements($user->id, 'certificate_earned', ['course_id' => 5]);

        $this->assertDatabaseHas('achievement_awards', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        Notification::assertSentTo($user, \App\Notifications\AchievementUnlockedNotification::class);
    }

    public function test_award_achievement_prevents_duplicate_awards()
    {
        Notification::fake();

        $user = User::factory()->create();
        $achievement = Achievement::factory()->create();

        $award1 = $this->achievementService->awardAchievement($user->id, $achievement->id);
        $award2 = $this->achievementService->awardAchievement($user->id, $achievement->id);

        $this->assertEquals($award1->id, $award2->id);
        $this->assertEquals(1, AchievementAward::count());
    }

    public function test_revoke_achievement_deletes_award()
    {
        $user = User::factory()->create();
        $achievement = Achievement::factory()->create();
        AchievementAward::factory()->create([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        $revoked = $this->achievementService->revokeAchievement($user->id, $achievement->id);

        $this->assertTrue($revoked);
        $this->assertDatabaseMissing('achievement_awards', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }
}
