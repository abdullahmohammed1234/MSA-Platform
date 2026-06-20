<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\User;
use App\Models\Progress;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class BadgeService
{
    /**
     * Get all badges with user unlocked status.
     */
    public function getBadgesForUser(int $userId): Collection
    {
        return Badge::with(['awards' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }])->get();
    }

    /**
     * Evaluate and award badges based on learning events.
     */
    public function evaluateBadges(int $userId, string $event, array $data = []): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $unlockedIds = BadgeAward::where('user_id', $userId)
            ->pluck('badge_id')
            ->toArray();

        $badges = Badge::whereNotIn('id', $unlockedIds)->get();

        foreach ($badges as $badge) {
            $shouldAward = false;

            switch ($badge->criteria_type) {
                case 'first_course_completed':
                    if ($event === 'certificate_earned') {
                        $completedCourses = Enrollment::where('user_id', $userId)->where('status', 'completed')->count();
                        if ($completedCourses >= 1) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'quiz_master':
                    // e.g. completed 5 quizzes with passing score
                    $passedQuizzesCount = QuizAttempt::where('user_id', $userId)
                        ->where('passed', true)
                        ->select('quiz_id')
                        ->distinct()
                        ->count();
                    $threshold = $badge->criteria_value['threshold'] ?? 5;
                    if ($passedQuizzesCount >= $threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'perfect_score':
                    if ($event === 'quiz_passed' && isset($data['score']) && (int)$data['score'] === 100) {
                        $shouldAward = true;
                    }
                    break;

                case 'consistent_learner':
                    // e.g. completed 10 lessons
                    $completedLessons = Progress::where('user_id', $userId)->where('completed', true)->count();
                    $threshold = $badge->criteria_value['threshold'] ?? 10;
                    if ($completedLessons >= $threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'volunteer_graduate':
                    if ($event === 'path_completed' && isset($data['path_slug']) && $data['path_slug'] === 'volunteer-path') {
                        $shouldAward = true;
                    }
                    break;

                case 'mentor_certified':
                    if ($event === 'path_completed' && isset($data['path_slug']) && $data['path_slug'] === 'mentor-path') {
                        $shouldAward = true;
                    }
                    break;

                case 'coordinator_certified':
                    if ($event === 'path_completed' && isset($data['path_slug']) && $data['path_slug'] === 'coordinator-path') {
                        $shouldAward = true;
                    }
                    break;

                case 'manual':
                default:
                    // Awarded manually by admin
                    break;
            }

            if ($shouldAward) {
                $this->awardBadge($userId, $badge->id);
            }
        }
    }

    /**
     * Award a badge to a user.
     */
    public function awardBadge(int $userId, int $badgeId): BadgeAward
    {
        $user = User::findOrFail($userId);
        $badge = Badge::findOrFail($badgeId);

        // Prevent duplicates
        $existing = BadgeAward::where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $award = BadgeAward::create([
            'user_id' => $userId,
            'badge_id' => $badgeId,
            'awarded_at' => now(),
        ]);

        // Send notifications
        $user->notify(new \App\Notifications\BadgeAwardedNotification($award));

        return $award;
    }

    /**
     * Revoke badge from user.
     */
    public function revokeBadge(int $userId, int $badgeId): bool
    {
        $award = BadgeAward::where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->first();

        if ($award) {
            return $award->delete();
        }

        return false;
    }
}
