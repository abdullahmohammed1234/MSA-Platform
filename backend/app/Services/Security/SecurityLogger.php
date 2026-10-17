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
        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $payload[$key] = '[SCRUBBED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->scrubPayload($value);
            }
        }

        return $payload;
    }

    /**
     * Substring match on the normalised key so variants such as
     * current_password, new_password_confirmation, refreshToken, or
     * X-XSRF-TOKEN are caught without listing each one.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalised = preg_replace('/[^a-z0-9]/', '', strtolower($key));

        if ($normalised === 'key') {
            return true;
        }

        foreach (['password', 'passwd', 'token', 'secret', 'apikey', 'credential', 'authorization', 'cookie', 'verificationcode', 'privatekey'] as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }
}
