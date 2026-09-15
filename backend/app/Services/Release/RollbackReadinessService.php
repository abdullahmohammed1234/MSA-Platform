<?php

namespace App\Services\Release;

use App\Models\PlatformRelease;
use App\Services\Lifecycle\MigrationReadinessService;

class RollbackReadinessService
{
    public function __construct(
        private MigrationReadinessService $migrationService
    ) {}

    /**
     * Evaluate rollback readiness and manual recovery feasibility for a release.
     */
    public function evaluateRollbackReadiness(PlatformRelease $release): array
    {
        $previousRelease = $release->previousRelease;
        $prevIdentifier = $previousRelease ? $previousRelease->release_identifier : 'None Recorded';

        $migrations = $this->migrationService->getMigrationStatus();
        $hasMigrations = false;

        foreach ($release->changes as $change) {
            if ($change->category === 'migration') {
                $hasMigrations = true;
                break;
            }
        }

        $blockers = [];
        $manualSteps = [];

        if (!$previousRelease) {
            $blockers[] = 'No previous release baseline record found in Release Registry.';
        }

        if ($hasMigrations) {
            $blockers[] = 'Release includes database schema migration(s). Rolling back requires verifying database migration compatibility or manual DB restore.';
            $manualSteps[] = 'Inspect pending/applied migrations and run "php artisan migrate:rollback" if migrations support down() method.';
        }

        $manualSteps[] = 'Revert code repository to previous tag/commit in cPanel Git Version Control or Git workspace.';
        $manualSteps[] = 'Verify environment configuration variables (.env) against previous release baseline.';
        $manualSteps[] = 'Clear application caches using "php artisan cache:clear" and "php artisan config:clear".';
        $manualSteps[] = 'Execute Phase 29 Deployment Readiness & Phase 30 Post-Release Verification to confirm platform stability.';

        $rollbackState = match (true) {
            empty($previousRelease) => 'ROLLBACK_UNAVAILABLE',
            $hasMigrations => 'ROLLBACK_MANUAL',
            default => 'ROLLBACK_MANUAL', // Always ROLLBACK_MANUAL under cPanel shared hosting without automated CI runner
        };

        return [
            'release_identifier' => $release->release_identifier,
            'rollback_readiness' => $rollbackState,
            'previous_release_identifier' => $prevIdentifier,
            'migration_rollback_compatible' => !$hasMigrations,
            'backup_verification_state' => 'NOT_VERIFIED',
            'backup_verification_note' => 'No automated backup verification agent detected in cPanel hosting environment.',
            'blockers' => $blockers,
            'manual_recovery_steps' => $manualSteps,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
