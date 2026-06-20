<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SecurityLogger
{
    /**
     * Log a security incident to the database and application log channels.
     *
     * @param string $eventType
     * @param string $severity
     * @param string|null $details
     * @param array|null $payload
     * @param int|null $userId
     * @return SecurityEvent
     */
    public function log(string $eventType, string $severity = 'medium', ?string $details = null, ?array $payload = null, ?int $userId = null): SecurityEvent
    {
        $resolvedUserId = $userId ?? Auth::id();
        
        $ipAddress = Request::ip();
        $userAgent = Request::header('User-Agent');
        $url = Request::fullUrl();
        $method = Request::method();

        // Scrub sensitive attributes from payload
        $scrubbedPayload = $payload ? $this->scrubPayload($payload) : null;

        // Store event in database
        $event = SecurityEvent::create([
            'user_id' => $resolvedUserId,
            'event_type' => $eventType,
            'severity' => $severity,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'url' => substr($url, 0, 255),
            'method' => $method,
            'payload' => $scrubbedPayload,
            'details' => $details,
        ]);

        // Output structured log for external SIEM log collectors (e.g. Splunk, ELK)
        $logMessage = sprintf(
            "[SECURITY_EVENT] type=%s severity=%s ip=%s user_id=%s url=%s details=%s",
            $eventType,
            $severity,
            $ipAddress,
            $resolvedUserId ?: 'anonymous',
            $url,
            $details
        );

        if ($severity === 'critical' || $severity === 'high') {
            Log::critical($logMessage, ['payload' => $scrubbedPayload]);
        } elseif ($severity === 'medium') {
            Log::warning($logMessage, ['payload' => $scrubbedPayload]);
        } else {
            Log::info($logMessage, ['payload' => $scrubbedPayload]);
        }

        return $event;
    }

    /**
     * Scrub credentials and private tokens from payload.
     *
     * @param array $payload
     * @return array
     */
    protected function scrubPayload(array $payload): array
    {
        $sensitiveKeys = [
            'password', 
            'password_confirmation', 
            'token', 
            'secret', 
            'key', 
            'credential', 
            'api_key', 
            'auth_token',
            'client_secret',
            'verification_code',
        ];
        
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->scrubPayload($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys)) {
                $payload[$key] = '[SCRUBBED]';
            }
        }
        
        return $payload;
    }
}
