<?php

namespace App\Services\Release;

use App\Models\PlatformChange;

class ChangeImpactAnalysisService
{
    /**
     * Perform deterministic impact analysis for a set of platform changes or a single change.
     */
    public function analyzeImpact(array $changes): array
    {
        $affectedApps = [];
        $affectedServices = [];
        $categories = [];
        $riskFactors = [];
        $maxImpactScore = 1; // 1: LOW, 2: MEDIUM, 3: HIGH, 4: CRITICAL

        foreach ($changes as $change) {
            $changeCategory = is_array($change) ? ($change['category'] ?? 'backend_code') : $change->category;
            $changeApps = is_array($change) ? ($change['affected_applications'] ?? []) : ($change->affected_applications ?? []);
            $changeServices = is_array($change) ? ($change['affected_services'] ?? []) : ($change->affected_services ?? []);
            $declaredImpact = is_array($change) ? ($change['impact_level'] ?? 'LOW') : ($change->impact_level ?? 'LOW');

            $categories[] = $changeCategory;

            if (is_array($changeApps)) {
                $affectedApps = array_merge($affectedApps, $changeApps);
            }
            if (is_array($changeServices)) {
                $affectedServices = array_merge($affectedServices, $changeServices);
            }

            // Category specific impact analysis rules
            switch ($changeCategory) {
                case 'migration':
                    $riskFactors[] = 'Includes database schema migration — potential lock or structure change.';
                    $maxImpactScore = max($maxImpactScore, 3);
                    $affectedServices[] = 'database';
                    break;

                case 'integration':
                    $riskFactors[] = 'Modifies external service provider integration (e.g. Square / Payment / SMTP).';
                    $maxImpactScore = max($maxImpactScore, 3);
                    break;

                case 'rbac':
                case 'application_access':
                    $riskFactors[] = 'Modifies platform security, permissions or application access boundaries.';
                    $maxImpactScore = max($maxImpactScore, 3);
                    break;

                case 'scheduled_job':
                case 'queue':
                    $riskFactors[] = 'Modifies background task or queue worker processing rules.';
                    $maxImpactScore = max($maxImpactScore, 2);
                    $affectedServices[] = 'queue';
                    break;

                case 'operational_rule':
                case 'remediation_action':
                    $riskFactors[] = 'Modifies automated remediation rules or operational action parameters.';
                    $maxImpactScore = max($maxImpactScore, 2);
                    break;
            }

            // Declared impact score check
            $declaredScore = match ($declaredImpact) {
                'CRITICAL' => 4,
                'HIGH' => 3,
                'MEDIUM' => 2,
                default => 1,
            };

            if ($declaredScore > $maxImpactScore) {
                $maxImpactScore = $declaredScore;
                $riskFactors[] = "Explicitly declared high-risk change level: {$declaredImpact}.";
            }
        }

        $affectedApps = array_values(array_unique($affectedApps));
        $affectedServices = array_values(array_unique($affectedServices));
        $categories = array_values(array_unique($categories));
        $riskFactors = array_values(array_unique($riskFactors));

        $overallImpact = match ($maxImpactScore) {
            4 => 'CRITICAL',
            3 => 'HIGH',
            2 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'overall_impact_level' => $overallImpact,
            'affected_applications' => $affectedApps,
            'affected_services' => $affectedServices,
            'change_categories' => $categories,
            'risk_factors' => $riskFactors,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
