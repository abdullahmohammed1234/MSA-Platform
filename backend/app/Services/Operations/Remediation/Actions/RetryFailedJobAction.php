<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\Remediation\OperationalActionInterface;
use App\Services\Queue\QueueManagementService;
use Illuminate\Support\Facades\DB;

class RetryFailedJobAction implements OperationalActionInterface
{
    public function __construct(
        private QueueManagementService $queueService
    ) {}

    public function getKey(): string
    {
        return 'platform.retry_failed_job';
    }

    public function getName(): string
    {
        return 'Retry Failed Queue Job';
    }

    public function getDescription(): string
    {
        return 'Re-enqueues failed system queue job for execution using QueueManagementService.';
    }

    public function getRiskLevel(): string
    {
        return 'medium';
    }

    public function getCooldownSeconds(): int
    {
        return 300;
    }

    public function getMaxFailureThreshold(): int
    {
        return 3;
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    public function getRequiredApprovalPermission(): ?string
    {
        return null;
    }

    public function getSupportedRuleKeys(): array
    {
        return [
            'platform_failed_jobs_threshold',
        ];
    }

    public function getRequiredPermission(): string
    {
        return 'platform.operations.execute';
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function validatePreconditions(OperationalAlert $alert): array
    {
        $failedJobUuid = $alert->source_id;

        $job = DB::table('failed_jobs')->where('uuid', $failedJobUuid)->first();

        if (! $job && numeric_is_id($failedJobUuid)) {
            $job = DB::table('failed_jobs')->where('id', $failedJobUuid)->first();
        }

        if (! $job) {
            return [
                'valid' => false,
                'reason' => "Failed queue job '{$failedJobUuid}' no longer exists in failed_jobs repository.",
                'target' => null,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $job,
        ];
    }

    public function execute(OperationalAlert $alert, OperationalActionExecution $execution, User $actor): array
    {
        $precheck = $this->validatePreconditions($alert);
        if (! $precheck['valid']) {
            return [
                'success' => false,
                'before_snapshot' => [],
                'after_snapshot' => [],
                'summary' => "Precondition failed: {$precheck['reason']}",
                'error_code' => 'PRECONDITION_FAILED',
                'error_message' => $precheck['reason'],
            ];
        }

        $job = $precheck['target'];

        $beforeSnapshot = [
            'job_id' => $job->id,
            'uuid' => $job->uuid ?? null,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'failed_at' => $job->failed_at,
        ];

        $retrySuccess = $this->queueService->retryJob($job->uuid ?? (string) $job->id);

        $afterSnapshot = [
            'job_id' => $job->id,
            'uuid' => $job->uuid ?? null,
            'retried' => $retrySuccess,
            'retried_at' => now()->toIso8601String(),
        ];

        if (! $retrySuccess) {
            return [
                'success' => false,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $afterSnapshot,
                'summary' => "Failed to retry queue job #{$job->id}.",
                'error_code' => 'RETRY_FAILED',
                'error_message' => "Queue management service returned failure when attempting to retry job #{$job->id}.",
            ];
        }

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => "Successfully retried failed queue job #{$job->id}.",
        ];
    }
}

function numeric_is_id(string $str): bool
{
    return is_numeric($str);
}
