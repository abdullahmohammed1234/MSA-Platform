<?php

namespace App\Volunteering\Services;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\VolunteerProfile;

class EligibilityEvaluator
{
    /**
     * Evaluate eligibility of a volunteer profile for a given opportunity.
     * Returns an array with boolean 'eligible' and array of string 'reasons'.
     */
    public function evaluate(VolunteerProfile $profile, Opportunity $opportunity): array
    {
        $reasons = [];
        $eligible = true;

        // Eager-load skills on profile and opportunity if not loaded
        if (!$profile->relationLoaded('skills')) {
            $profile->load('skills');
        }
        if (!$opportunity->relationLoaded('skills')) {
            $opportunity->load('skills');
        }

        // 1. Check Opportunity Hard Required Skills
        $requiredSkills = $opportunity->skills->where('pivot.is_required', true);
        $userSkillsMap = $profile->skills->keyBy('id');

        foreach ($requiredSkills as $reqSkill) {
            if (!$userSkillsMap->has($reqSkill->id)) {
                $eligible = false;
                $reasons[] = "Missing required skill: {$reqSkill->name}";
            } else {
                // Check minimum proficiency if specified
                $userSkill = $userSkillsMap->get($reqSkill->id);
                $minProf = $reqSkill->pivot->min_proficiency ?? 'beginner';
                $userProf = $userSkill->pivot->proficiency_level ?? 'beginner';

                if ($this->proficiencyRank($userProf) < $this->proficiencyRank($minProf)) {
                    $eligible = false;
                    $reasons[] = "Skill '{$reqSkill->name}' proficiency ({$userProf}) below required ({$minProf})";
                }
            }
        }

        // 2. Check Opportunity Status Invariants
        if ($opportunity->status !== 'open') {
            $eligible = false;
            $reasons[] = "Opportunity is currently not accepting signups ({$opportunity->status})";
        }

        if ($eligible && empty($reasons)) {
            $reasons[] = 'All required eligibility criteria satisfied';
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
        ];
    }

    protected function proficiencyRank(string $level): int
    {
        return match (strtolower($level)) {
            'expert' => 4,
            'advanced' => 3,
            'intermediate' => 2,
            'beginner' => 1,
            default => 1,
        };
    }
}
