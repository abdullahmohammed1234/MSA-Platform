<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Mlibms\Models\Loan;

use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\Remediation\OperationalActionInterface;

class RecalculateLoanStatusAction implements OperationalActionInterface
{
    public function getKey(): string
    {
        return 'mlibms.recalculate_loan_status';
    }

    public function getName(): string
    {
        return 'Recalculate Library Loan Status';
    }

    public function getDescription(): string
    {
        return 'Recalculates overdue status and days overdue for active library loan.';
    }

    public function getRiskLevel(): string
    {
        return 'low';
    }

    public function getCooldownSeconds(): int
    {
        return 120;
    }

    public function getMaxFailureThreshold(): int
    {
        return 5;
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
            'mlibms_loan_overdue_7d',
            'mlibms_overdue_loan_unresolved',
        ];
    }

    public function getRequiredPermission(): string
    {
        return 'platform.operations.execute';
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function validatePreconditions(OperationalAlert $alert): array
    {
        $loanId = $alert->source_id;
        $loan = Loan::with(['bookCopy', 'member'])->find($loanId);

        if (! $loan) {
            return [
                'valid' => false,
                'reason' => "Library loan #{$loanId} no longer exists.",
                'target' => null,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $loan,
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

        /** @var Loan $loan */
        $loan = $precheck['target'];

        $beforeSnapshot = [
            'loan_id' => $loan->id,
            'status' => $loan->status,
            'due_at' => $loan->due_at?->toIso8601String(),
            'returned_at' => $loan->returned_at?->toIso8601String(),
        ];

        // Recalculate overdue state
        if ($loan->returned_at === null && $loan->due_at && $loan->due_at->isPast()) {
            $loan->status = 'overdue';
            $loan->save();
        } elseif ($loan->returned_at !== null) {
            $loan->status = 'returned';
            $loan->save();
        }

        $afterSnapshot = [
            'loan_id' => $loan->id,
            'status' => $loan->status,
            'due_at' => $loan->due_at?->toIso8601String(),
            'returned_at' => $loan->returned_at?->toIso8601String(),
        ];

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => "Successfully recalculated loan status for loan #{$loan->id} (status: {$loan->status}).",
        ];
    }
}
