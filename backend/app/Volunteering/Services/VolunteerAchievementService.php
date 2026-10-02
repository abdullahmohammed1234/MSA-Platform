<?php

namespace App\Volunteering\Services;

use App\Models\User;
use App\Volunteering\Models\Achievement;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\UserAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VolunteerAchievementService
{
    public function __construct(
        private readonly VolunteerSignupService $signupService,
        private readonly VmsNotificationDispatcher $notificationDispatcher
    ) {
    }

    /**
     * Evaluate and award eligible automatic achievements for a user.
     *
     * @return Collection<int, UserAchievement>
     */
    public function evaluateUserAchievements(User $user): Collection
    {
        $activeAchievements = Achievement::query()
            ->where('is_active', true)
            ->get();

        if ($activeAchievements->isEmpty()) {
            return collect();
        }

        $existingAchievementIds = UserAchievement::query()
            ->where('user_id', $user->id)
            ->pluck('achievement_id')
            ->toArray();

        // Calculate evaluation metrics
        $serviceHoursSummary = $this->signupService->getUserHistory($user->id);
        $totalServiceHours = (float) ($serviceHoursSummary['total_service_hours'] ?? 0.0);
        $completedCount = (int) ($serviceHoursSummary['completed_count'] ?? 0);
        $attendanceCount = Signup::query()
            ->where('user_id', $user->id)
            ->where('attendance_status', 'present')
            ->count();
        $profileCompletion = (int) ($user->volunteerProfile?->profile_completion_percentage ?? 0);

        $newlyAwarded = collect();

        foreach ($activeAchievements as $achievement) {
            if (in_array($achievement->id, $existingAchievementIds, true)) {
                continue;
            }

            $isEligible = false;
            $criteria = $achievement->criteria_config ?? [];
            $threshold = $criteria['threshold'] ?? 1;

            switch ($achievement->rule_type) {
                case 'completed_opportunities':
                    $isEligible = ($completedCount >= (int) $threshold);
                    break;
                case 'verified_service_hours':
                    $isEligible = ($totalServiceHours >= (float) $threshold);
                    break;
                case 'attendance_count':
                    $isEligible = ($attendanceCount >= (int) $threshold);
                    break;
                case 'profile_completion':
                    $isEligible = ($profileCompletion >= (int) $threshold);
                    break;
                default:
                    $isEligible = false;
            }

            if ($isEligible) {
                $userAchievement = $this->awardAchievement(
                    user: $user,
                    achievement: $achievement,
                    awardedBy: null,
                    triggerType: 'automatic',
                    reason: "Automated award for satisfying {$achievement->rule_type} threshold ({$threshold}).",
                    metadata: [
                        'total_service_hours' => $totalServiceHours,
                        'completed_count' => $completedCount,
                        'attendance_count' => $attendanceCount,
                        'profile_completion' => $profileCompletion,
                    ]
                );

                if ($userAchievement) {
                    $newlyAwarded->push($userAchievement);
                }
            }
        }

        return $newlyAwarded;
    }

    /**
     * Manually award an achievement by an administrator.
     */
    public function awardManualAchievement(User $user, int $achievementId, User $adminUser, ?string $reason = null): ?UserAchievement
    {
        $achievement = Achievement::query()->findOrFail($achievementId);

        return $this->awardAchievement(
            user: $user,
            achievement: $achievement,
            awardedBy: $adminUser,
            triggerType: 'manual',
            reason: $reason ?? "Manually awarded by coordinator {$adminUser->name}.",
            metadata: [
                'awarded_by_id' => $adminUser->id,
                'awarded_by_name' => $adminUser->name,
            ]
        );
    }

    /**
     * Safely award an achievement ensuring idempotency and post-commit transactional notifications.
     */
    private function awardAchievement(
        User $user,
        Achievement $achievement,
        ?User $awardedBy,
        string $triggerType,
        ?string $reason,
        array $metadata = []
    ): ?UserAchievement {
        $existing = UserAchievement::query()
            ->where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->first();

        if ($existing !== null) {
            return null;
        }

        $userAchievement = DB::transaction(function () use ($user, $achievement, $awardedBy, $triggerType, $reason, $metadata) {
            $record = UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'awarded_at' => now(),
                'awarded_by' => $awardedBy?->id,
                'trigger_type' => $triggerType,
                'award_reason' => $reason,
                'metadata' => $metadata,
            ]);

            DB::afterCommit(function () use ($user, $achievement, $record) {
                Log::info('vms.achievement.awarded', [
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'achievement_slug' => $achievement->slug,
                    'user_achievement_id' => $record->id,
                ]);
            });

            return $record;
        });

        return $userAchievement;
    }

    /**
     * Get user's full recognition summary.
     */
    public function getUserRecognitionSummary(User $user): array
    {
        $userAchievements = UserAchievement::query()
            ->with('achievement')
            ->where('user_id', $user->id)
            ->orderBy('awarded_at', 'desc')
            ->get();

        $totalPoints = $userAchievements->sum(fn ($ua) => $ua->achievement?->points ?? 0);

        return [
            'earned_count' => $userAchievements->count(),
            'total_points' => $totalPoints,
            'achievements' => $userAchievements,
        ];
    }
}
