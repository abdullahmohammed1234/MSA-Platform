<?php

namespace App\Services\Operations\Remediation;

use App\Models\OperationalAlert;
use App\Services\Operations\Remediation\Actions\IssueMissingTicketAction;
use App\Services\Operations\Remediation\Actions\RecalculateLoanStatusAction;
use App\Services\Operations\Remediation\Actions\RecheckOrderFulfillmentAction;
use App\Services\Operations\Remediation\Actions\RecheckRefundStatusAction;
use App\Services\Operations\Remediation\Actions\RefreshPendingBacklogAction;
use App\Services\Operations\Remediation\Actions\RetryFailedJobAction;
use App\Services\Operations\Remediation\Actions\RetryFailedNotificationAction;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

class OperationalActionRegistry
{
    /** @var array<string, class-string<OperationalActionInterface>> */
    private array $actionClasses = [
        'ems.issue_missing_ticket' => IssueMissingTicketAction::class,
        'communications.retry_failed_notification' => RetryFailedNotificationAction::class,
        'platform.retry_failed_job' => RetryFailedJobAction::class,
        'mlibms.recalculate_loan_status' => RecalculateLoanStatusAction::class,
        'store.recheck_order_fulfillment' => RecheckOrderFulfillmentAction::class,
        'volunteers.refresh_pending_backlog' => RefreshPendingBacklogAction::class,
        'donations.recheck_refund_status' => RecheckRefundStatusAction::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    public function getAction(string $actionKey): ?OperationalActionInterface
    {
        if (! isset($this->actionClasses[$actionKey])) {
            return null;
        }

        return $this->container->make($this->actionClasses[$actionKey]);
    }

    /**
     * @return Collection<int, OperationalActionInterface>
     */
    public function getAllActions(): Collection
    {
        return collect($this->actionClasses)->map(function ($class) {
            return $this->container->make($class);
        });
    }

    /**
     * Get available remediation actions for a specific alert with evaluated preconditions.
     */
    public function getActionsForAlert(OperationalAlert $alert): array
    {
        $matchingActions = [];

        foreach ($this->getAllActions() as $action) {
            if (in_array($alert->rule_key, $action->getSupportedRuleKeys(), true)) {
                $precondition = $action->validatePreconditions($alert);

                $matchingActions[] = [
                    'key' => $action->getKey(),
                    'name' => $action->getName(),
                    'description' => $action->getDescription(),
                    'risk_level' => $action->getRiskLevel(),
                    'cooldown_seconds' => $action->getCooldownSeconds(),
                    'max_failure_threshold' => $action->getMaxFailureThreshold(),
                    'requires_approval' => $action->requiresApproval(),
                    'required_permission' => $action->getRequiredPermission(),
                    'requires_confirmation' => $action->requiresConfirmation(),
                    'is_reversible' => $action->isReversible(),
                    'precondition' => [
                        'valid' => $precondition['valid'],
                        'reason' => $precondition['reason'],
                    ],
                ];
            }
        }

        return $matchingActions;
    }
}
