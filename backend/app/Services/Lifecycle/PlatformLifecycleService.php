<?php

namespace App\Services\Lifecycle;

use App\Models\User;
use App\Services\ApplicationAccessService;

class PlatformLifecycleService
{
    public function __construct(
        private EnvironmentInventoryService $envService,
        private MigrationReadinessService $migrationService,
        private DependencyInventoryService $dependencyService,
        private SchedulerQueueInventoryService $schedulerQueueService,
        private ConfigurationDriftService $driftService,
        private DeploymentReadinessService $readinessService,
        private ApplicationAccessService $appAccessService
    ) {}

    /**
     * Get complete aggregated Platform Lifecycle Overview payload for Central Admin.
     */
    public function getLifecycleOverview(User $actor): array
    {
        $environment = $this->envService->getEnvironmentInventory();
        $release = $this->envService->getReleaseMetadata();
        $migrations = $this->migrationService->getMigrationStatus();
        $dependencies = $this->dependencyService->getDependencyHealth();
        $schedulerQueue = $this->schedulerQueueService->getSchedulerQueueInventory();
        $drift = $this->driftService->detectConfigurationDrift();
        $readiness = $this->readinessService->evaluateReadiness();

        // Scope capabilities based on actor's application access if restricted
        if (!$actor->hasAnyRole(['super-admin', 'admin'])) {
            $accessibleApps = $this->appAccessService->getUserAccessibleApplications($actor);
            $environment['enabled_capabilities'] = array_intersect_key(
                $environment['enabled_capabilities'],
                array_flip($accessibleApps)
            );
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'readiness' => $readiness,
            'release' => $release,
            'environment' => $environment,
            'migrations' => $migrations,
            'dependencies' => $dependencies,
            'scheduler_queue' => $schedulerQueue,
            'drift' => $drift,
        ];
    }

    public function getEnvironmentInventory(): array
    {
        return $this->envService->getEnvironmentInventory();
    }

    public function getReleaseMetadata(): array
    {
        return $this->envService->getReleaseMetadata();
    }

    public function getMigrationStatus(): array
    {
        return $this->migrationService->getMigrationStatus();
    }

    public function getDependencyHealth(): array
    {
        return $this->dependencyService->getDependencyHealth();
    }

    public function getSchedulerQueueInventory(): array
    {
        return $this->schedulerQueueService->getSchedulerQueueInventory();
    }

    public function detectConfigurationDrift(): array
    {
        return $this->driftService->detectConfigurationDrift();
    }

    public function evaluateReadiness(): array
    {
        return $this->readinessService->evaluateReadiness();
    }
}
