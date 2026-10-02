<?php

namespace App\Volunteering\Services;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\VolunteerProfile;
use App\Models\User;

class VolunteerMatchingService
{
    public function __construct(
        protected EligibilityEvaluator $eligibilityEvaluator
    ) {}

    /**
     * Calculate compatibility match score and detailed explanations for a profile and opportunity.
     */
    public function calculateMatch(VolunteerProfile $profile, Opportunity $opportunity): array
    {
        // 1. Evaluate Hard Eligibility
        $eligibility = $this->eligibilityEvaluator->evaluate($profile, $opportunity);
        if (!$eligibility['eligible']) {
            return [
                'score' => 0,
                'grade' => 'Ineligible',
                'eligible' => false,
                'reasons' => $eligibility['reasons'],
                'skill_match_count' => 0,
                'interest_match_count' => 0,
            ];
        }

        $score = 0;
        $explanations = [];

        // Eager load relations
        $profile->loadMissing(['skills', 'interests', 'experiences', 'user']);
        $opportunity->loadMissing(['skills', 'interests', 'shifts']);

        // 2. Skill Alignment (Max 35 points)
        $skillScore = 0;
        $matchedSkillCount = 0;

        $oppSkills = $opportunity->skills;
        $userSkills = $profile->skills->keyBy('id');

        if ($oppSkills->count() > 0) {
            $requiredSkills = $oppSkills->where('pivot.is_required', true);
            $preferredSkills = $oppSkills->where('pivot.is_required', false);

            if ($requiredSkills->count() > 0) {
                // Hard skills satisfied (20 points base)
                $skillScore += 20;
                $explanations[] = "Satisfies all {$requiredSkills->count()} required skill(s)";
            }

            if ($preferredSkills->count() > 0) {
                $prefMatched = 0;
                foreach ($preferredSkills as $pSkill) {
                    if ($userSkills->has($pSkill->id)) {
                        $prefMatched++;
                        $matchedSkillCount++;
                    }
                }
                $prefRatio = $prefMatched / $preferredSkills->count();
                $prefPoints = (int) round($prefRatio * 15);
                $skillScore += $prefPoints;

                if ($prefMatched > 0) {
                    $explanations[] = "Matches {$prefMatched} of {$preferredSkills->count()} preferred skill(s)";
                }
            } else if ($requiredSkills->count() > 0) {
                $skillScore += 15; // Full skill points if no preferred skills defined
            }
        } else {
            // General opportunity with no skill requirements
            $skillScore = 25;
            $explanations[] = "Open to general volunteer skills";
        }
        $score += min(35, $skillScore);

        // 3. Interest Alignment (Max 25 points)
        $interestScore = 0;
        $matchedInterestCount = 0;
        $oppInterests = $opportunity->interests;
        $userInterests = $profile->interests->keyBy('id');

        if ($oppInterests->count() > 0) {
            foreach ($oppInterests as $interest) {
                if ($userInterests->has($interest->id)) {
                    $matchedInterestCount++;
                }
            }
            if ($matchedInterestCount > 0) {
                $interestRatio = $matchedInterestCount / $oppInterests->count();
                $interestScore = (int) round($interestRatio * 25);
                $explanations[] = "Matches {$matchedInterestCount} volunteering interest(s)";
            }
        } else {
            $interestScore = 15; // Default neutral interest score
        }
        $score += min(25, $interestScore);

        // 4. Experience & VMS-6 Verified Service Hours (Max 20 points)
        $expScore = match (strtolower($profile->experience_level)) {
            'expert' => 15,
            'advanced' => 12,
            'intermediate' => 9,
            'beginner' => 6,
            default => 5,
        };

        // Derive VMS-6 Verified Service Hours
        $verifiedHours = $this->calculateVerifiedServiceHours($profile->user_id);
        if ($verifiedHours > 0) {
            $bonus = min(5, (int) floor($verifiedHours / 5));
            $expScore += $bonus;
            $explanations[] = "Earned {$verifiedHours} verified MSA service hours";
        } else if ($profile->experiences->count() > 0) {
            $expScore += 3;
            $explanations[] = "Has prior external volunteering experience";
        }
        $score += min(20, $expScore);

        // 5. Availability & Preferences Alignment (Max 20 points)
        $availScore = 10; // Base score
        if (!empty($profile->availability_days) && $opportunity->start_at) {
            $dayOfWeek = strtolower($opportunity->start_at->format('l'));
            if (in_array($dayOfWeek, array_map('strtolower', (array) $profile->availability_days))) {
                $availScore += 10;
                $explanations[] = "Available on opportunity day ({$dayOfWeek})";
            }
        } else {
            $availScore = 15;
        }
        $score += min(20, $availScore);

        // Final Bounded Score
        $score = max(1, min(100, $score));

        $grade = match (true) {
            $score >= 80 => 'Strong Match',
            $score >= 60 => 'Good Match',
            $score >= 40 => 'Moderate Match',
            default => 'Basic Match',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'eligible' => true,
            'reasons' => array_values(array_unique($explanations)),
            'skill_match_count' => $matchedSkillCount,
            'interest_match_count' => $matchedInterestCount,
        ];
    }

    /**
     * Find candidate matches for an opportunity (Coordinator view).
     */
    public function findMatchesForOpportunity(int $opportunityId, int $limit = 20): array
    {
        $opportunity = Opportunity::with(['skills', 'interests', 'shifts'])->findOrFail($opportunityId);

        // Fetch active profiles with users
        $profiles = VolunteerProfile::with(['user:id,name,email', 'skills', 'interests', 'experiences'])
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
            })
            ->get();

        $matches = [];

        foreach ($profiles as $profile) {
            $matchResult = $this->calculateMatch($profile, $opportunity);

            if ($matchResult['eligible']) {
                $matches[] = [
                    'profile_id' => $profile->id,
                    'user_id' => $profile->user_id,
                    'user_name' => $profile->user?->name ?? 'Volunteer',
                    'user_email' => $profile->user?->email ?? '',
                    'experience_level' => $profile->experience_level,
                    'score' => $matchResult['score'],
                    'grade' => $matchResult['grade'],
                    'reasons' => $matchResult['reasons'],
                    'verified_service_hours' => $this->calculateVerifiedServiceHours($profile->user_id),
                ];
            }
        }

        // Deterministic sort by score DESC, verified_service_hours DESC, user_id ASC
        usort($matches, function ($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($b['verified_service_hours'] !== $a['verified_service_hours']) {
                return $b['verified_service_hours'] <=> $a['verified_service_hours'];
            }
            return $a['user_id'] <=> $b['user_id'];
        });

        return array_slice($matches, 0, $limit);
    }

    /**
     * Get recommended opportunities for a volunteer user (Volunteer view).
     */
    public function getRecommendationsForVolunteer(int $userId, int $limit = 10): array
    {
        $profile = VolunteerProfile::with(['skills', 'interests', 'experiences'])
            ->where('user_id', $userId)
            ->first();

        if (!$profile) {
            return [];
        }

        $opportunities = Opportunity::published()
            ->with(['event:id,name,slug,start_at,location', 'skills', 'interests', 'shifts'])
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '>=', now()->subDays(1));
            })
            ->get();

        $recommendations = [];

        foreach ($opportunities as $opportunity) {
            $matchResult = $this->calculateMatch($profile, $opportunity);

            if ($matchResult['eligible'] && $matchResult['score'] >= 30) {
                $recommendations[] = [
                    'opportunity' => [
                        'id' => $opportunity->id,
                        'uuid' => $opportunity->uuid,
                        'title' => $opportunity->title,
                        'slug' => $opportunity->slug,
                        'description' => $opportunity->description,
                        'start_at' => $opportunity->start_at?->toIso8601String(),
                        'location' => $opportunity->location,
                        'capacity' => $opportunity->capacity,
                        'status' => $opportunity->status,
                        'event' => $opportunity->event ? [
                            'id' => $opportunity->event->id,
                            'name' => $opportunity->event->name,
                            'slug' => $opportunity->event->slug,
                        ] : null,
                    ],
                    'match_score' => $matchResult['score'],
                    'match_grade' => $matchResult['grade'],
                    'reasons' => $matchResult['reasons'],
                ];
            }
        }

        // Deterministic sort by match_score DESC, opportunity id ASC
        usort($recommendations, function ($a, $b) {
            if ($b['match_score'] !== $a['match_score']) {
                return $b['match_score'] <=> $a['match_score'];
            }
            return $a['opportunity']['id'] <=> $b['opportunity']['id'];
        });

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Calculate authoritative VMS-6 service hours for a user.
     */
    protected function calculateVerifiedServiceHours(int $userId): float
    {
        $signups = Signup::with('shift')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhere(function ($q) {
                        $q->where('status', 'confirmed')
                          ->where('attendance_status', 'present');
                    });
            })
            ->get();

        $totalHours = 0.0;
        foreach ($signups as $s) {
            if ($s->shift && $s->shift->start_at && $s->shift->end_at) {
                $mins = abs($s->shift->start_at->diffInMinutes($s->shift->end_at));
                $totalHours += ($mins / 60.0);
            }
        }

        return round($totalHours, 1);
    }
}
