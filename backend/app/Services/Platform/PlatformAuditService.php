<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class PlatformAuditService
{
    /**
     * Search and filter audit logs with high-performance indexed queries.
     */
    public function searchLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::with('user:id,name,email');

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

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPageClamped);
    }

    /**
     * Prune audit logs older than the given number of days.
     */
    public function prune(int $days = 180): int
    {
        $cutoff = Carbon::now()->subDays($days);
        return AuditLog::where('created_at', '<', $cutoff)->delete();
    }
}
