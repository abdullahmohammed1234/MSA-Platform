<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\BadgeServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BadgeService $badgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->badgeService = new BadgeService();
    }

    public function test_get_badges_for_user()
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();
        BadgeAward::factory()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);

        $results = $this->badgeService->getBadgesForUser($user->id);

        $this->assertCount(1, $results);
        $this->assertNotEmpty($results->first()->awards);
    }

    public function test_evaluate_badges_awards_on_criteria_match()
    {
        Notification::fake();

        $user = User::factory()->create();
        $badge = Badge::factory()->create([
            'criteria_type' => 'perfect_score',
            'criteria_value' => null,
        ]);

        $this->badgeService->evaluateBadges($user->id, 'quiz_passed', ['score' => 100]);

        $this->assertDatabaseHas('badge_awards', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);

        Notification::assertSentTo($user, \App\Notifications\BadgeAwardedNotification::class);
    }

    public function test_award_badge_prevents_duplicate_awards()
    {
        Notification::fake();

        $user = User::factory()->create();
        $badge = Badge::factory()->create();

        $award1 = $this->badgeService->awardBadge($user->id, $badge->id);
        $award2 = $this->badgeService->awardBadge($user->id, $badge->id);

        $this->assertEquals($award1->id, $award2->id);
        $this->assertEquals(1, BadgeAward::count());
    }

    public function test_revoke_badge_deletes_award()
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();
        BadgeAward::factory()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);

        $revoked = $this->badgeService->revokeBadge($user->id, $badge->id);

        $this->assertTrue($revoked);
        $this->assertDatabaseMissing('badge_awards', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);
    }
}
