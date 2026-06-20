<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log an administrative or sensitive system action.
     *
     * @param string $action
     * @param Model|null $target
     * @param string|null $description
     * @param array|null $payload
     * @param int|null $userId
     * @return AuditLog
     */
    public function log(string $action, ?Model $target = null, ?string $description = null, ?array $payload = null, ?int $userId = null): AuditLog
    {
        $resolvedUserId = $userId ?? Auth::id();
        
        $ipAddress = Request::ip();
        $userAgent = Request::header('User-Agent');

        return AuditLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target ? $target->getKey() : null,
            'description' => $description,
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
        ]);
    }
}
