<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\MilestoneAward;
use App\Models\User;
use App\Models\Progress;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Collection;

class MilestoneService
{
    /**
     * Get all milestones for a user with earned status.
     */
    public function getMilestonesForUser(int $userId): Collection
    {
        return Milestone::with(['awards' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }])->get();
    }

    /**
     * Evaluates and awards milestones for a user.
     */
    public function checkMilestones(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        // Fetch milestone awards already earned
        $earnedIds = MilestoneAward::where('user_id', $userId)
            ->pluck('milestone_id')
            ->toArray();

        $milestones = Milestone::whereNotIn('id', $earnedIds)->get();

        // Count metrics once
        $completedLessonsCount = Progress::where('user_id', $userId)->where('completed', true)->count();
        $completedCoursesCount = Enrollment::where('user_id', $userId)->where('status', 'completed')->count();
        
        // Count completed learning paths (by looking at path certificates awarded)
        $completedPathsCount = \App\Models\CertificateAward::where('user_id', $userId)
            ->whereHas('certificate', function ($query) {
                $query->where('type', 'learning_path');
            })->count();

        foreach ($milestones as $milestone) {
            $shouldAward = false;

            switch ($milestone->type) {
                case 'lessons_completed':
                    if ($completedLessonsCount >= $milestone->threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'courses_completed':
                    if ($completedCoursesCount >= $milestone->threshold) {
                        $shouldAward = true;
                    }
                    break;

                case 'paths_completed':
                    if ($completedPathsCount >= $milestone->threshold) {
                        $shouldAward = true;
                    }
                    break;
            }

            if ($shouldAward) {
                $this->awardMilestone($userId, $milestone->id);
            }
        }
    }

    /**
     * Award a milestone.
     */
    public function awardMilestone(int $userId, int $milestoneId): MilestoneAward
    {
        $existing = MilestoneAward::where('user_id', $userId)
            ->where('milestone_id', $milestoneId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return MilestoneAward::create([
            'user_id' => $userId,
            'milestone_id' => $milestoneId,
            'awarded_at' => now(),
        ]);
    }
}
