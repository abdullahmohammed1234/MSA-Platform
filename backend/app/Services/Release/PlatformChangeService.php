<?php

namespace App\Services\Release;

use App\Models\AuditLog;
use App\Models\PlatformChange;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PlatformChangeService
{
    public function __construct(
        private ChangeImpactAnalysisService $impactAnalysisService,
        private ApplicationAccessService $appAccessService
    ) {}

    /**
     * Register a new platform operational change.
     */
    public function registerChange(array $data, User $author): PlatformChange
    {
        $changeId = $data['change_identifier'] ?? ('CHG-' . date('Ymd-His') . '-' . Str::random(4));

        // Evaluate impact level if not explicitly passed
        $impactAnalysis = $this->impactAnalysisService->analyzeImpact([$data]);
        $impactLevel = $data['impact_level'] ?? $impactAnalysis['overall_impact_level'];

        $change = PlatformChange::create([
            'release_id' => $data['release_id'] ?? null,
            'change_identifier' => strtoupper($changeId),
            'category' => $data['category'] ?? 'backend_code',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'reason' => $data['reason'] ?? null,
            'author_id' => $author->id,
            'environment' => $data['environment'] ?? config('app.env', 'production'),
            'affected_applications' => $data['affected_applications'] ?? $impactAnalysis['affected_applications'],
            'affected_services' => $data['affected_services'] ?? $impactAnalysis['affected_services'],
            'impact_level' => $impactLevel,
            'validation_status' => $data['validation_status'] ?? 'NOT_VERIFIED',
            'audit_log_id' => $data['audit_log_id'] ?? null,
        ]);

        // Record audit log entry
        AuditLog::create([
            'user_id' => $author->id,
            'action' => 'platform.change.register',
            'application' => 'platform',
            'severity' => 'info',
            'description' => "Registered platform change {$change->change_identifier}: {$change->title}",
            'payload' => [
                'change_identifier' => $change->change_identifier,
                'category' => $change->category,
                'impact_level' => $change->impact_level,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $change->fresh(['author', 'release']);
    }

    /**
     * List and search platform operational changes with filters and pagination.
     */
    public function listChanges(array $filters, int $perPage, User $actor): LengthAwarePaginator
    {
        $query = PlatformChange::with(['author', 'release']);

        if (!empty($filters['release_id'])) {
            $query->where('release_id', $filters['release_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['impact_level'])) {
            $query->where('impact_level', $filters['impact_level']);
        }

        if (!empty($filters['validation_status'])) {
            $query->where('validation_status', $filters['validation_status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('change_identifier', 'like', $search)
                  ->orWhere('title', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhere('reason', 'like', $search);
            });
        }

        // Domain access filter for restricted admins
        if (!$actor->hasAnyRole(['super-admin', 'admin'])) {
            $accessibleApps = $this->appAccessService->getUserAccessibleApplications($actor);
            $query->where(function ($q) use ($accessibleApps) {
                foreach ($accessibleApps as $app) {
                    $q->orWhereJsonContains('affected_applications', $app);
                }
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get details of a specific platform change by ID or UUID or identifier.
     */
    public function getChangeDetail(string $identifier): PlatformChange
    {
        return PlatformChange::with(['author', 'release', 'auditLog'])
            ->where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->orWhere('change_identifier', $identifier)
            ->firstOrFail();
    }
}
