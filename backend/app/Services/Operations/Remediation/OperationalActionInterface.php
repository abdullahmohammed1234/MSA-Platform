<?php

namespace App\Services\Operations\Remediation;

use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;

interface OperationalActionInterface
{
    /**
     * Unique action key identifier (e.g. 'ems.issue_missing_ticket').
     */
    public function getKey(): string;

    /**
     * Human-readable title for UI.
     */
    public function getName(): string;

    /**
     * Human-readable explanation of remediation behavior.
     */
    public function getDescription(): string;

    /**
     * Risk classification level ('low' | 'medium' | 'high' | 'critical').
     */
    public function getRiskLevel(): string;

    /**
     * Cooldown duration in seconds before repeat execution is permitted on the same alert.
     */
    public function getCooldownSeconds(): int;

    /**
     * Maximum consecutive failure count before automation is blocked for manual investigation.
     */
    public function getMaxFailureThreshold(): int;

    /**
     * Whether elevated administrator approval is required prior to execution.
     */
    public function requiresApproval(): bool;

    /**
     * Permission required to approve/reject elevated approval requests.
     */
    public function getRequiredApprovalPermission(): ?string;

    /**
     * Rule keys supported by this action.
     *
     * @return array<string>
     */
    public function getSupportedRuleKeys(): array;

    /**
     * Required permission for execution.
     */
    public function getRequiredPermission(): string;

    /**
     * Whether explicit UI confirmation is required.
     */
    public function requiresConfirmation(): bool;

    /**
     * Whether the remediation is reversible.
     */
    public function isReversible(): bool;

    /**
     * Revalidate current resource state immediately before mutation.
     *
     * @return array{valid: bool, reason: ?string, target: mixed}
     */
    public function validatePreconditions(OperationalAlert $alert): array;

    /**
     * Execute the deterministic remediation logic.
     *
     * @return array{success: bool, before_snapshot: array, after_snapshot: array, summary: string, error_code?: string, error_message?: string}
     */
    public function execute(OperationalAlert $alert, OperationalActionExecution $execution, User $actor): array;
}
