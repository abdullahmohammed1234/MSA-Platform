<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\User;
use App\Models\Progress;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class AchievementService
{
    /**
     * Get all achievements with user unlocked status.
     */
    public function getAchievementsForUser(int $userId): Collection
    {
        return Achievement::with(['awards' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }])->get();
    }

    /**
     * Evaluate and award achievements based on learning events.
     */
    public function evaluateAchievements(int $userId, string $event, array $data = []): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        // Fetch achievements that the user has not unlocked yet
        $unlockedIds = AchievementAward::where('user_id', $userId)
            ->pluck('achievement_id')
            ->toArray();

        $achievements = Achievement::whereNotIn('id', $unlockedIds)->get();

        foreach ($achievements as $achievement) {
            $shouldAward = false;

            switch ($achievement->criteria_type) {
                case 'course_completed':
                    if ($event === 'certificate_earned' && isset($data['course_id'])) {
                        $targetCourseId = $achievement->criteria_value['course_id'] ?? null;
                        if ($targetCourseId && (int)$targetCourseId === (int)$data['course_id']) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'quiz_passed':
                    if ($event === 'quiz_passed' && isset($data['quiz_id'])) {
                        $targetQuizId = $achievement->criteria_value['quiz_id'] ?? null;
                        if ($targetQuizId && (int)$targetQuizId === (int)$data['quiz_id']) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'score_reached':
                    if ($event === 'quiz_passed' && isset($data['score'])) {
                        $targetScore = $achievement->criteria_value['min_score'] ?? 100;
                        if ((int)$data['score'] >= (int)$targetScore) {
                            // Optionally restrict to a specific quiz if specified
                            $targetQuizId = $achievement->criteria_value['quiz_id'] ?? null;
                            if (!$targetQuizId || (int)$targetQuizId === (int)($data['quiz_id'] ?? 0)) {
                                $shouldAward = true;
                            }
                        }
                    }
                    break;

                case 'lessons_count':
                    $threshold = $achievement->criteria_value['threshold'] ?? 1;
                    $completedLessons = Progress::where('user_id', $userId)->where('completed', true)->count();
                    if ($completedLessons >= $threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'courses_count':
                    $threshold = $achievement->criteria_value['threshold'] ?? 1;
                    $completedCourses = Enrollment::where('user_id', $userId)->where('status', 'completed')->count();
                    if ($completedCourses >= $threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'path_completed':
                    if ($event === 'path_completed' && isset($data['learning_path_id'])) {
                        $targetPathId = $achievement->criteria_value['learning_path_id'] ?? null;
                        if ($targetPathId && (int)$targetPathId === (int)$data['learning_path_id']) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'manual':
                default:
                    // Only awarded manually by admin
                    break;
            }

            if ($shouldAward) {
                $this->awardAchievement($userId, $achievement->id);
            }
        }
    }

    /**
     * Lock/Award an achievement to a user.
     */
    public function awardAchievement(int $userId, int $achievementId): AchievementAward
    {
        $user = User::findOrFail($userId);
        $achievement = Achievement::findOrFail($achievementId);

        // Prevent duplicates
        $existing = AchievementAward::where('user_id', $userId)
            ->where('achievement_id', $achievementId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $award = AchievementAward::create([
            'user_id' => $userId,
            'achievement_id' => $achievementId,
            'unlocked_at' => now(),
        ]);

        // Send notifications
        $user->notify(new \App\Notifications\AchievementUnlockedNotification($award));

        // Evaluate milestones since unlocking an achievement is an event
        app(MilestoneService::class)->checkMilestones($userId);

        return $award;
    }

    /**
     * Revoke achievement from user.
     */
    public function revokeAchievement(int $userId, int $achievementId): bool
    {
        $award = AchievementAward::where('user_id', $userId)
            ->where('achievement_id', $achievementId)
            ->first();

        if ($award) {
            return $award->delete();
        }

        return false;
    }
}
