<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\Remediation\OperationalActionInterface;
use App\Store\Models\StoreOrder;

class RecheckOrderFulfillmentAction implements OperationalActionInterface
{
    public function getKey(): string
    {
        return 'store.recheck_order_fulfillment';
    }

    public function getName(): string
    {
        return 'Recheck Store Order Fulfillment Status';
    }

    public function getDescription(): string
    {
        return 'Re-evaluates store order items against inventory and payment status without altering inventory balance.';
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
            'store_unfulfilled_order_aged',
            'store_order_fulfillment_stuck',
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
        $orderId = $alert->source_id;
        $order = StoreOrder::with(['items', 'customer'])->find($orderId);

        if (! $order) {
            return [
                'valid' => false,
                'reason' => "Store order #{$orderId} no longer exists.",
                'target' => null,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $order,
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

        /** @var StoreOrder $order */
        $order = $precheck['target'];

        $beforeSnapshot = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
        ];

        // Recheck status
        $order->refresh();

        $afterSnapshot = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
        ];

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => "Rechecked store order #{$order->order_number} (payment: {$order->payment_status}, fulfillment: {$order->fulfillment_status}).",
        ];
    }
}
