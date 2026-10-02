<?php

namespace App\Volunteering\Services;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\ReengagementLog;
use App\Volunteering\Models\Signup;

class VolunteerEngagementService
{
    public function __construct(
        private readonly VolunteerSignupService $signupService,
        private readonly VolunteerAchievementService $achievementService,
        private readonly VolunteerMatchingService $matchingService
    ) {
    }

    /**
     * Get engagement metrics and retention status for a specific volunteer.
     */
    public function getVolunteerEngagementMetrics(User $user): array
    {
        $summary = $this->signupService->getUserHistory($user->id);
        $recognition = $this->achievementService->getUserRecognitionSummary($user);

        // Find last completed/confirmed-present participation date
        $lastSignup = Signup::query()
            ->with(['shift', 'opportunity'])
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'confirmed')
                            ->where('attendance_status', 'present');
                    });
            })
            ->orderBy('created_at', 'desc')
            ->first();

        $lastParticipatedAt = null;
        if ($lastSignup) {
            if ($lastSignup->shift?->end_at) {
                $lastParticipatedAt = $lastSignup->shift->end_at;
            } elseif ($lastSignup->opportunity?->start_at && $lastSignup->opportunity->start_at->isPast()) {
                $lastParticipatedAt = $lastSignup->opportunity->start_at;
            } else {
                $lastParticipatedAt = $lastSignup->attended_at ?? $lastSignup->created_at;
            }
        }

        $daysInactive = $lastParticipatedAt ? (int) abs(now()->diffInDays($lastParticipatedAt)) : null;

        // Classify retention status
        $status = 'new';
        $completedCount = (int) ($summary['completed_count'] ?? 0);

        if ($completedCount === 0) {
            $status = 'new';
        } elseif ($daysInactive !== null && $daysInactive <= 30) {
            $status = 'active';
        } elseif ($daysInactive !== null && $daysInactive <= 60 && $completedCount >= 2) {
            $status = 'returning';
        } elseif ($daysInactive !== null && $daysInactive <= 120) {
            $status = 'inactive';
        } else {
            $status = 'dormant';
        }

        // Calculate next milestone progress
        $totalHours = (float) ($summary['total_service_hours'] ?? 0.0);
        $milestoneThresholds = [5.0, 10.0, 25.0, 50.0, 100.0];
        $nextMilestone = null;

        foreach ($milestoneThresholds as $threshold) {
            if ($totalHours < $threshold) {
                $nextMilestone = [
                    'threshold_hours' => $threshold,
                    'current_hours' => $totalHours,
                    'remaining_hours' => round($threshold - $totalHours, 1),
                    'progress_percentage' => (int) min(100, round(($totalHours / $threshold) * 100)),
                ];
                break;
            }
        }

        return [
            'user_id' => $user->id,
            'volunteer_name' => $user->name,
            'total_service_hours' => round($totalHours, 1),
            'completed_count' => $completedCount,
            'total_signups' => (int) ($summary['total_signups'] ?? 0),
            'active_signups_count' => (int) ($summary['active_count'] ?? 0),
            'last_participated_at' => $lastParticipatedAt?->toIso8601String(),
            'days_since_last_participation' => $daysInactive,
            'retention_status' => $status,
            'earned_achievements_count' => $recognition['earned_count'],
            'earned_points' => $recognition['total_points'],
            'next_milestone' => $nextMilestone,
        ];
    }

    /**
     * Get aggregate retention breakdown across all volunteers for admin intelligence.
     */
    public function getRetentionOverview(): array
    {
        $users = User::query()
            ->whereHas('applicationAccess', fn ($q) => $q->where('application', 'volunteering'))
            ->orWhereHas('volunteerProfile')
            ->orWhereHas('volunteeringSignups')
            ->get();

        $breakdown = [
            'active' => 0,
            'returning' => 0,
            'inactive' => 0,
            'dormant' => 0,
            'new' => 0,
            'total_volunteers' => $users->count(),
        ];

        foreach ($users as $u) {
            $metrics = $this->getVolunteerEngagementMetrics($u);
            $st = $metrics['retention_status'];
            if (isset($breakdown[$st])) {
                $breakdown[$st]++;
            }
        }

        return $breakdown;
    }

    /**
     * Identify inactive/dormant volunteers suitable for re-engagement along with matching open opportunities.
     */
    public function getReengagementCandidates(int $limit = 20): array
    {
        $users = User::query()
            ->whereHas('volunteerProfile')
            ->get();

        $openOpportunities = Opportunity::query()
            ->where('status', 'published')
            ->where('start_at', '>', now())
            ->get();

        $candidates = [];

        foreach ($users as $u) {
            $metrics = $this->getVolunteerEngagementMetrics($u);
            if (! in_array($metrics['retention_status'], ['inactive', 'dormant'], true)) {
                continue;
            }

            // Find top matching opportunity for this inactive volunteer using VMS-7 matching engine
            $topMatches = [];
            $profile = $u->volunteerProfile;
            if ($profile) {
                foreach ($openOpportunities as $opp) {
                    $match = $this->matchingService->calculateMatch($profile, $opp);
                    if ($match['eligible'] && ($match['score'] ?? 0) >= 40) {
                        $topMatches[] = [
                            'opportunity_id' => $opp->id,
                            'opportunity_title' => $opp->title,
                            'match_score' => $match['score'],
                            'grade' => $match['grade'] ?? 'Good Match',
                            'reasons' => $match['reasons'] ?? [],
                        ];
                    }
                }
            }

            usort($topMatches, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

            if (! empty($topMatches)) {
                $candidates[] = [
                    'user_id' => $u->id,
                    'user_name' => $u->name,
                    'user_email' => $u->email,
                    'retention_status' => $metrics['retention_status'],
                    'days_since_last_participation' => $metrics['days_since_last_participation'],
                    'total_service_hours' => $metrics['total_service_hours'],
                    'top_match' => $topMatches[0],
                ];
            }

            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * Record a re-engagement outreach attempt.
     */
    public function logReengagementOutreach(int $userId, ?int $opportunityId = null, string $channel = 'email'): ReengagementLog
    {
        return ReengagementLog::create([
            'user_id' => $userId,
            'opportunity_id' => $opportunityId,
            'channel' => $channel,
            'sent_at' => now(),
        ]);
    }
}
