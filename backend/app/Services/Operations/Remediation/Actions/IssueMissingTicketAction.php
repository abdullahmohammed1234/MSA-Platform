<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Registration;
use App\Ems\Services\Ticketing\DefaultTicketIssuer;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\Remediation\OperationalActionInterface;

class IssueMissingTicketAction implements OperationalActionInterface
{
    public function __construct(
        private DefaultTicketIssuer $ticketIssuer
    ) {}

    public function getKey(): string
    {
        return 'ems.issue_missing_ticket';
    }

    public function getName(): string
    {
        return 'Issue Missing Ticket';
    }

    public function getDescription(): string
    {
        return 'Confirms paid registration and generates unique QR ticket payload reusing official EMS ticket issuer.';
    }

    public function getRiskLevel(): string
    {
        return 'high';
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
        return true;
    }

    public function getRequiredApprovalPermission(): ?string
    {
        return 'platform.operations.approve';
    }

    public function getSupportedRuleKeys(): array
    {
        return [
            'ems_payment_ticket_mismatch',
            'ems_reconciled_square_ticket_missing',
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
        $registrationId = $alert->source_id;
        $registration = Registration::with(['payments', 'tickets', 'event'])->find($registrationId);

        if (! $registration) {
            return [
                'valid' => false,
                'reason' => "Registration #{$registrationId} no longer exists.",
                'target' => null,
            ];
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            return [
                'valid' => false,
                'reason' => "Registration #{$registration->reference} is cancelled.",
                'target' => $registration,
            ];
        }

        if ($registration->tickets()->exists()) {
            return [
                'valid' => false,
                'reason' => "Registration #{$registration->reference} already has tickets issued.",
                'target' => $registration,
            ];
        }

        $paidPayment = $registration->payments()->where('status', 'paid')->exists();
        if (! $paidPayment) {
            return [
                'valid' => false,
                'reason' => "Registration #{$registration->reference} does not have a settled paid payment.",
                'target' => $registration,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $registration,
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

        /** @var Registration $registration */
        $registration = $precheck['target'];

        $beforeSnapshot = [
            'registration_id' => $registration->id,
            'reference' => $registration->reference,
            'status' => $registration->status->value ?? (string) $registration->status,
            'ticket_count' => $registration->tickets()->count(),
        ];

        // Ensure registration status is Confirmed before calling ticket issuer
        if ($registration->status !== RegistrationStatus::Confirmed) {
            $registration->status = RegistrationStatus::Confirmed;
            $registration->confirmed_at = now();
            $registration->save();
        }

        $tickets = $this->ticketIssuer->issueFor($registration->fresh());

        $afterRegistration = $registration->fresh(['tickets']);

        $afterSnapshot = [
            'registration_id' => $afterRegistration->id,
            'reference' => $afterRegistration->reference,
            'status' => $afterRegistration->status->value ?? (string) $afterRegistration->status,
            'ticket_count' => $tickets->count(),
            'issued_ticket_codes' => $tickets->pluck('code')->toArray(),
        ];

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => "Successfully issued {$tickets->count()} ticket(s) for registration #{$registration->reference}.",
        ];
    }
}
