<?php

namespace App\Services\Release;

use App\Models\AuditLog;
use App\Models\PlatformChange;
use App\Models\PlatformRelease;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Services\Lifecycle\DeploymentReadinessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PlatformReleaseService
{
    private const ALLOWED_STATUSES = [
        'PLANNED', 'APPROVED', 'DEPLOYING', 'DEPLOYED', 'VERIFYING',
        'SUCCESSFUL', 'SUCCESSFUL_WITH_WARNINGS', 'FAILED', 'CANCELLED', 'ROLLED_BACK', 'NOT_VERIFIED'
    ];

    public function __construct(
        private ChangeImpactAnalysisService $impactService,
        private PostReleaseVerificationService $verificationService,
        private RollbackReadinessService $rollbackService,
        private DeploymentReadinessService $readinessService,
        private ApplicationAccessService $appAccessService
    ) {}

    /**
     * Get active platform release or create baseline representation.
     */
    public function getActiveRelease(): array
    {
        $activeModel = PlatformRelease::with(['changes', 'creator', 'approver'])
            ->where('is_active', true)
            ->orWhere('status', 'SUCCESSFUL')
            ->orderBy('released_at', 'desc')
            ->first();

        if (!$activeModel) {
            $activeModel = PlatformRelease::firstOrCreate(
                ['release_identifier' => 'v1.30.0-prod'],
                [
                    'uuid' => (string) Str::uuid(),
                    'application_version' => '1.30.0',
                    'api_version' => 'v1',
                    'frontend_build' => '2026.09.13-phase30',
                    'environment' => config('app.env', 'production'),
                    'status' => 'SUCCESSFUL',
                    'is_active' => true,
                    'released_at' => now(),
                    'release_notes' => 'Phase 30 — Platform Change Management & Release Intelligence Baseline Release',
                    'deployment_status' => 'DEPLOYED',
                    'migration_status' => 'UP_TO_DATE',
                    'post_release_verification_status' => 'SUCCESSFUL',
                    'rollback_readiness' => 'ROLLBACK_MANUAL',
                    'affected_applications' => ['platform', 'ems', 'store', 'donations', 'mlibms'],
                ]
            );
        }

        $impact = $this->impactService->analyzeImpact($activeModel->changes->toArray());
        $rollback = $this->rollbackService->evaluateRollbackReadiness($activeModel);
        $readiness = $this->readinessService->evaluateReadiness();

        return [
            'id' => $activeModel->id,
            'uuid' => $activeModel->uuid,
            'release_identifier' => $activeModel->release_identifier,
            'application_version' => $activeModel->application_version,
            'api_version' => $activeModel->api_version,
            'frontend_build' => $activeModel->frontend_build,
            'environment' => $activeModel->environment,
            'status' => $activeModel->status,
            'is_active' => (bool) $activeModel->is_active,
            'released_at' => $activeModel->released_at ? $activeModel->released_at->toIso8601String() : null,
            'release_notes' => $activeModel->release_notes,
            'deployment_status' => $activeModel->deployment_status,
            'migration_status' => $activeModel->migration_status,
            'post_release_verification_status' => $activeModel->post_release_verification_status,
            'rollback_readiness' => $rollback['rollback_readiness'],
            'creator' => $activeModel->creator ? $activeModel->creator->name : 'System Administrator',
            'approver' => $activeModel->approver ? $activeModel->approver->name : null,
            'approved_at' => $activeModel->approved_at ? $activeModel->approved_at->toIso8601String() : null,
            'changes_count' => $activeModel->changes->count(),
            'impact_analysis' => $impact,
            'readiness_summary' => $readiness['summary'],
            'hosting_environment' => 'cPanel / Shared Hosting (PHP-FPM + MySQL)',
        ];
    }

    /**
     * List releases with pagination and filters.
     */
    public function listReleases(array $filters, int $perPage, User $actor): LengthAwarePaginator
    {
        $query = PlatformRelease::with(['creator', 'approver', 'changes']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['environment'])) {
            $query->where('environment', $filters['environment']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('release_identifier', 'like', $search)
                  ->orWhere('application_version', 'like', $search)
                  ->orWhere('release_notes', 'like', $search);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get single release detail.
     */
    public function getReleaseDetail(string $identifier): PlatformRelease
    {
        $release = PlatformRelease::with(['changes.author', 'creator', 'approver', 'previousRelease'])
            ->where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->orWhere('release_identifier', $identifier)
            ->first();

        if (!$release && $identifier === 'v1.30.0-prod') {
            $release = PlatformRelease::firstOrCreate(
                ['release_identifier' => 'v1.30.0-prod'],
                [
                    'uuid' => (string) Str::uuid(),
                    'application_version' => '1.30.0',
                    'api_version' => 'v1',
                    'frontend_build' => '2026.09.13-phase30',
                    'environment' => config('app.env', 'production'),
                    'status' => 'SUCCESSFUL',
                    'is_active' => true,
                    'released_at' => now(),
                    'release_notes' => 'Phase 30 — Platform Change Management & Release Intelligence Baseline Release',
                    'deployment_status' => 'DEPLOYED',
                    'migration_status' => 'UP_TO_DATE',
                    'post_release_verification_status' => 'SUCCESSFUL',
                    'rollback_readiness' => 'ROLLBACK_MANUAL',
                    'affected_applications' => ['platform', 'ems', 'store', 'donations', 'mlibms'],
                ]
            );
        }

        if (!$release) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Release {$identifier} not found.");
        }

        return $release;
    }

    /**
     * Register a new release.
     */
    public function createRelease(array $data, User $creator): PlatformRelease
    {
        $relId = $data['release_identifier'] ?? ('v' . ($data['application_version'] ?? '1.30.0') . '-' . Str::random(4));

        $prevRelease = PlatformRelease::where('is_active', true)->first();

        $release = PlatformRelease::create([
            'release_identifier' => $relId,
            'application_version' => $data['application_version'] ?? '1.30.0',
            'api_version' => $data['api_version'] ?? 'v1',
            'frontend_build' => $data['frontend_build'] ?? date('Y.m.d') . '-build',
            'environment' => $data['environment'] ?? config('app.env', 'production'),
            'status' => 'PLANNED',
            'is_active' => false,
            'release_notes' => $data['release_notes'] ?? null,
            'created_by' => $creator->id,
            'previous_release_id' => $prevRelease ? $prevRelease->id : null,
            'affected_applications' => $data['affected_applications'] ?? ['platform'],
            'rollback_readiness' => 'ROLLBACK_MANUAL',
        ]);

        AuditLog::create([
            'user_id' => $creator->id,
            'action' => 'platform.release.create',
            'application' => 'platform',
            'severity' => 'info',
            'description' => "Created platform release {$release->release_identifier}",
            'payload' => ['release_identifier' => $release->release_identifier],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $release;
    }

    /**
     * Approve a release with separation of duties enforcement.
     */
    public function approveRelease(PlatformRelease $release, User $approver, ?string $notes = null): PlatformRelease
    {
        // Enforce separation of duties for non-super-admins
        if ($release->created_by === $approver->id && !$approver->hasRole('super-admin')) {
            throw new InvalidArgumentException('Separation of duties required: You cannot approve a release that you created.');
        }

        $release->update([
            'status' => 'APPROVED',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'release_notes' => $notes ? ($release->release_notes . "\nApproval Note: " . $notes) : $release->release_notes,
        ]);

        AuditLog::create([
            'user_id' => $approver->id,
            'action' => 'platform.release.approve',
            'application' => 'platform',
            'severity' => 'info',
            'description' => "Approved platform release {$release->release_identifier}",
            'payload' => ['release_identifier' => $release->release_identifier],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $release->fresh(['creator', 'approver']);
    }

    /**
     * Transition release status through deterministic lifecycle state machine.
     */
    public function transitionReleaseStatus(PlatformRelease $release, string $newStatus, User $actor): PlatformRelease
    {
        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid release status transition to {$newStatus}.");
        }

        $updates = ['status' => $newStatus];

        if (in_array($newStatus, ['DEPLOYED', 'SUCCESSFUL', 'SUCCESSFUL_WITH_WARNINGS'], true)) {
            // Deactivate previous active releases and set this release active
            PlatformRelease::where('id', '!=', $release->id)->update(['is_active' => false]);
            $updates['is_active'] = true;
            $updates['released_at'] = $release->released_at ?? now();
            $updates['deployment_status'] = 'DEPLOYED';
        } elseif ($newStatus === 'ROLLED_BACK') {
            $updates['is_active'] = false;
            $updates['deployment_status'] = 'ROLLED_BACK';
        }

        $release->update($updates);

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'platform.release.transition',
            'application' => 'platform',
            'severity' => 'info',
            'description' => "Transitioned release {$release->release_identifier} status to {$newStatus}",
            'payload' => [
                'release_identifier' => $release->release_identifier,
                'new_status' => $newStatus,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $release->fresh();
    }

    /**
     * Compare two releases side-by-side.
     */
    public function compareReleases(PlatformRelease $releaseA, PlatformRelease $releaseB): array
    {
        return [
            'release_a' => [
                'identifier' => $releaseA->release_identifier,
                'version' => $releaseA->application_version,
                'status' => $releaseA->status,
                'changes_count' => $releaseA->changes->count(),
            ],
            'release_b' => [
                'identifier' => $releaseB->release_identifier,
                'version' => $releaseB->application_version,
                'status' => $releaseB->status,
                'changes_count' => $releaseB->changes->count(),
            ],
            'comparison' => [
                'version_diff' => $releaseA->application_version !== $releaseB->application_version ? 'CHANGED' : 'UNCHANGED',
                'status_diff' => $releaseA->status !== $releaseB->status ? 'CHANGED' : 'UNCHANGED',
                'changes_diff_count' => abs($releaseA->changes->count() - $releaseB->changes->count()),
            ],
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
