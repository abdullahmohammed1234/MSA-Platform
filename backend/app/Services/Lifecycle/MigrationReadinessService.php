<?php

namespace App\Services\Lifecycle;

use Throwable;

class MigrationReadinessService
{
    /**
     * Get migration readiness status and pending migration details.
     */
    public function getMigrationStatus(): array
    {
        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (!$repository->repositoryExists()) {
                return [
                    'status' => 'UNKNOWN',
                    'is_up_to_date' => false,
                    'applied_count' => 0,
                    'pending_count' => 0,
                    'latest_applied' => null,
                    'pending_migrations' => [],
                    'details' => 'Migration repository table does not exist in database.',
                    'last_checked_at' => now()->toIso8601String(),
                ];
            }

            $ran = $repository->getRan();
            $files = $migrator->getMigrationFiles(database_path('migrations'));

            $pending = [];
            foreach ($files as $name => $path) {
                if (!in_array($name, $ran, true)) {
                    $pending[] = $name;
                }
            }

            $appliedCount = count($ran);
            $pendingCount = count($pending);
            $latestApplied = end($ran) ?: null;

            $status = $pendingCount === 0 ? 'UP_TO_DATE' : 'PENDING_MIGRATIONS';

            return [
                'status' => $status,
                'is_up_to_date' => $pendingCount === 0,
                'applied_count' => $appliedCount,
                'pending_count' => $pendingCount,
                'latest_applied' => $latestApplied,
                'pending_migrations' => array_values($pending),
                'details' => $pendingCount === 0
                    ? 'All database migrations are current.'
                    : "There are {$pendingCount} pending migration(s) that have not been executed.",
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'UNKNOWN',
                'is_up_to_date' => false,
                'applied_count' => 0,
                'pending_count' => 0,
                'latest_applied' => null,
                'pending_migrations' => [],
                'details' => 'Migration status check failed: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }
}
