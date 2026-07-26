<?php

namespace App\Ems\Services;

use App\Models\AuditLog;
use App\Services\Security\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * The EMS audit and structured-logging foundation.
 *
 * Rather than standing up a second audit store, this writes to the platform's
 * existing `audit_logs` table through the existing AuditLogger, namespacing
 * every EMS action with an `ems.` prefix. That keeps one audit trail for the
 * whole MSA platform and means the existing /api/v1/admin/audit-logs screen
 * shows EMS activity for free.
 *
 * Alongside the audit row it emits a structured record on the dedicated `ems`
 * log channel for operational tooling.
 *
 * Sensitive keys are stripped from every payload before it reaches either
 * destination.
 */
class EmsActivityLogger
{
    public const PREFIX = 'ems.';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_DENIED = 'denied';
    public const RESULT_FAILED = 'failed';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Record a successful EMS action.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        ?Model $subject = null,
        string $description = '',
        array $context = [],
        string $result = self::RESULT_SUCCESS,
        string $level = 'info',
    ): ?AuditLog {
        $safeContext = $this->redact($context);

        $record = array_filter([
            'action' => self::PREFIX . $action,
            'result' => $result,
            'user_id' => Auth::id(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => Request::ip(),
            'context' => $safeContext === [] ? null : $safeContext,
        ], static fn ($value): bool => $value !== null);

        Log::channel($this->channel())->{$level}($description !== '' ? $description : $action, $record);

        return $this->auditLogger->log(
            self::PREFIX . $action,
            $subject,
            $description !== '' ? $description : null,
            array_merge($safeContext, ['result' => $result]),
        );
    }

    /**
     * Record an authorization failure. These are the entries that matter most
     * during an incident review, so they are logged at warning level.
     *
     * @param  array<string, mixed>  $context
     */
    public function denied(string $action, ?Model $subject = null, string $description = '', array $context = []): void
    {
        $this->log(
            $action,
            $subject,
            $description !== '' ? $description : sprintf('Authorization denied for %s.', $action),
            $context,
            self::RESULT_DENIED,
            'warning',
        );
    }

    /**
     * Record a handled failure such as an illegal lifecycle transition.
     *
     * @param  array<string, mixed>  $context
     */
    public function failed(string $action, ?Model $subject = null, string $description = '', array $context = []): void
    {
        $this->log($action, $subject, $description, $context, self::RESULT_FAILED, 'warning');
    }

    /**
     * Log an unexpected error without persisting an audit row. Used by the
     * exception handler, where an audit entry would be noise.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        Log::channel($this->channel())->error($message, $this->redact($context));
    }

    /**
     * Strip credentials and payment secrets before anything is written.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        /** @var array<int, string> $redactedKeys */
        $redactedKeys = config('ems.logging.redacted_keys', []);

        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $redactedKeys, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function channel(): string
    {
        $channel = (string) config('ems.logging.channel', 'ems');

        // Fall back to the default stack if the channel was never published,
        // so a misconfigured deployment loses log detail rather than requests.
        return config("logging.channels.{$channel}") === null
            ? config('logging.default', 'stack')
            : $channel;
    }
}
