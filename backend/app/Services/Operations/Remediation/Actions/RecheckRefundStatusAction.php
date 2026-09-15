<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Donations\Models\DonationRefund;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\Remediation\OperationalActionInterface;

class RecheckRefundStatusAction implements OperationalActionInterface
{
    public function getKey(): string
    {
        return 'donations.recheck_refund_status';
    }

    public function getName(): string
    {
        return 'Recheck Donation Refund Reconciliation Status';
    }

    public function getDescription(): string
    {
        return 'Re-evaluates donation refund status and gateway reconciliation state without executing new financial transactions.';
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
            'donation_refund_unreconciled',
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
        $refundId = $alert->source_id;
        $refund = DonationRefund::with('donation')->find($refundId);

        if (! $refund) {
            return [
                'valid' => false,
                'reason' => "Donation refund #{$refundId} no longer exists.",
                'target' => null,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $refund,
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

        /** @var DonationRefund $refund */
        $refund = $precheck['target'];

        $beforeSnapshot = [
            'refund_id' => $refund->id,
            'donation_id' => $refund->donation_id,
            'amount_cents' => $refund->amount_cents,
            'status' => $refund->status,
            'square_refund_id' => $refund->square_refund_id,
        ];

        // Refresh model from storage to ensure we have latest out-of-band updates
        $refund->refresh();

        $afterSnapshot = [
            'refund_id' => $refund->id,
            'donation_id' => $refund->donation_id,
            'amount_cents' => $refund->amount_cents,
            'status' => $refund->status,
            'square_refund_id' => $refund->square_refund_id,
            'reconciled' => ! in_array($refund->status, ['pending', 'failed']) && ! empty($refund->square_refund_id),
        ];

        $reconciled = $afterSnapshot['reconciled'];
        $summary = $reconciled
            ? "Donation refund #{$refund->id} is reconciled (status: {$refund->status}, gateway ID: {$refund->square_refund_id})."
            : "Donation refund #{$refund->id} status is '{$refund->status}' and requires manual administrator action in Donations module.";

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => $summary,
        ];
    }
}
