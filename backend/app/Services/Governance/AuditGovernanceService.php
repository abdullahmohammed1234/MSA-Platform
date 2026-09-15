<?php

namespace App\Services\Governance;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditGovernanceService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'card',
        'cvv',
        'private_key',
        'auth_token',
        'access_token',
    ];

    public function __construct(
        private ApplicationAccessService $accessService
    ) {}

    /**
     * Search and filter audit logs with RBAC and ApplicationAccess privacy enforcement.
     */
    public function searchAuditLogs(array $filters = [], int $perPage = 20, ?User $user = null): LengthAwarePaginator
    {
        $query = AuditLog::with('user:id,name,email');

        // Application boundary filtering for non-privileged admin
        if ($user && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $accessibleApps = array_keys(array_filter(
                $this->accessService->accessibleApplications($user),
                fn ($details) => $details['access'] === true
            ));
            // Include platform/security logs plus authorized domain logs
            $allowedApps = array_unique(array_merge($accessibleApps, ['platform', 'security', 'operations']));
            $query->where(function ($q) use ($allowedApps) {
                $q->whereIn('application', $allowedApps)
                  ->orWhereNull('application');
            });
        }

        if (! empty($filters['application'])) {
            $query->where('application', $filters['application']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', 'like', '%' . $filters['action'] . '%');
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['target_type'])) {
            $query->where('target_type', 'like', '%' . $filters['target_type'] . '%');
        }

        if (! empty($filters['target_id'])) {
            $query->where('target_id', (int) $filters['target_id']);
        }

        if (! empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                  ->orWhere('action', 'like', $searchTerm)
                  ->orWhere('ip_address', 'like', $searchTerm);
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $perPageClamped = min(100, max(5, $perPage));
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPageClamped);

        // Sanitize payloads
        $paginator->getCollection()->transform(function (AuditLog $log) {
            if ($log->payload && is_array($log->payload)) {
                $log->payload = $this->sanitizePayload($log->payload);
            }
            return $log;
        });

        return $paginator;
    }

    /**
     * Retrieve change accountability records (WHO, WHAT, WHEN, WHERE, WHY, RESULT).
     */
    public function getChangeAccountability(string $period = '7d', int $limit = 20, ?User $user = null): array
    {
        $startDate = match ($period) {
            'today' => Carbon::now()->startOfDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'this_year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->subDays(7),
        };

        $query = AuditLog::with('user:id,name,email')
            ->where('created_at', '>=', $startDate);

        // RBAC Domain Access Check
        if ($user && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $accessibleApps = array_keys(array_filter(
                $this->accessService->accessibleApplications($user),
                fn ($details) => $details['access'] === true
            ));
            $allowedApps = array_unique(array_merge($accessibleApps, ['platform', 'security', 'operations']));
            $query->where(function ($q) use ($allowedApps) {
                $q->whereIn('application', $allowedApps)
                  ->orWhereNull('application');
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->take($limit * 2)->get();

        $accountabilityRecords = [];

        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $this->sanitizePayload($log->payload) : [];

            // Extract explicit reason if present in payload
            $why = $payload['reason'] 
                ?? $payload['request_reason'] 
                ?? $payload['decision_reason'] 
                ?? $payload['justification'] 
                ?? 'Reason not recorded';

            $result = $payload['result'] 
                ?? $payload['status'] 
                ?? ucfirst($log->severity) 
                ?? 'Completed';

            $accountabilityRecords[] = [
                'id' => $log->id,
                'who' => [
                    'id' => $log->user_id,
                    'name' => $log->user?->name ?? 'System / Anonymous',
                    'email' => $log->user?->email ?? 'N/A',
                ],
                'what' => [
                    'action' => $log->action,
                    'description' => $log->description ?? $log->action,
                    'severity' => $log->severity,
                ],
                'when' => [
                    'timestamp' => $log->created_at->toIso8601String(),
                    'human' => $log->created_at->diffForHumans(),
                ],
                'where' => [
                    'domain' => $log->application ?? 'System',
                    'target_type' => $log->target_type ? class_basename($log->target_type) : null,
                    'target_id' => $log->target_id,
                    'ip_address' => $log->ip_address,
                ],
                'why' => $why,
                'result' => $result,
                'payload' => $payload,
            ];

            if (count($accountabilityRecords) >= $limit) {
                break;
            }
        }

        return $accountabilityRecords;
    }

    /**
     * Recursively sanitize sensitive keys in payloads.
     */
    public function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
