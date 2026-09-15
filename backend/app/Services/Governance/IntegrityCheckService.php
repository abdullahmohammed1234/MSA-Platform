<?php

namespace App\Services\Governance;

use App\Services\ApplicationAccessService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityCheckService
{
    public function __construct(
        private ApplicationAccessService $accessService
    ) {}

    /**
     * Run deterministic domain data integrity diagnostics.
     */
    public function runIntegrityScan(?string $domainFilter = null, ?User $user = null): array
    {
        $checks = [];

        // EMS Domain Checks
        if ($this->shouldRunDomain('ems', $domainFilter, $user)) {
            $checks[] = $this->checkEmsPaidRegistrationsMissingTickets();
            $checks[] = $this->checkEmsDuplicateQrCodes();
            $checks[] = $this->checkEmsConfirmedPaymentFailedMismatch();
        }

        // Donations Domain Checks
        if ($this->shouldRunDomain('donations', $domainFilter, $user)) {
            $checks[] = $this->checkDonationsMissingTransactionReference();
            $checks[] = $this->checkDonationsInvalidAmounts();
        }

        // Store Domain Checks
        if ($this->shouldRunDomain('store', $domainFilter, $user)) {
            $checks[] = $this->checkStorePaidOrdersMissingFulfillmentStatus();
            $checks[] = $this->checkStoreNegativeInventory();
        }

        // MLibMS Domain Checks
        if ($this->shouldRunDomain('mlibms', $domainFilter, $user)) {
            $checks[] = $this->checkMlibmsLoansMissingBooks();
            $checks[] = $this->checkMlibmsImpossibleReturnState();
        }

        // Operations Domain Checks
        if ($this->shouldRunDomain('operations', $domainFilter, $user)) {
            $checks[] = $this->checkOperationsExecutionsMissingAlerts();
            $checks[] = $this->checkOperationsApprovalsMissingAlerts();
        }

        $passedCount = count(array_filter($checks, fn ($c) => $c['status'] === 'PASSED'));
        $warningCount = count(array_filter($checks, fn ($c) => $c['status'] === 'WARNING'));
        $failedCount = count(array_filter($checks, fn ($c) => $c['status'] === 'FAILED'));

        $overallStatus = match (true) {
            $failedCount > 0 => 'FAILED',
            $warningCount > 0 => 'WARNING',
            default => 'PASSED',
        };

        return [
            'status' => $overallStatus,
            'scanned_at' => now()->toIso8601String(),
            'total_checks' => count($checks),
            'passed_count' => $passedCount,
            'warning_count' => $warningCount,
            'failed_count' => $failedCount,
            'checks' => $checks,
        ];
    }

    private function shouldRunDomain(string $domain, ?string $domainFilter, ?User $user): bool
    {
        if ($domainFilter && strtolower($domainFilter) !== $domain) {
            return false;
        }

        if ($user && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            if (in_array($domain, ['ems', 'donations', 'store', 'mlibms'], true)) {
                return $this->accessService->canAccess($user, $domain);
            }
        }

        return true;
    }

    // ----------------------------------------------------
    // EMS INTEGRITY CHECKS
    // ----------------------------------------------------

    private function checkEmsPaidRegistrationsMissingTickets(): array
    {
        if (! Schema::hasTable('ems_registrations')) {
            return $this->makeCheckResult('EMS', 'paid_registrations_without_tickets', 'PASSED', 0, 'ems_registrations table not present');
        }

        $count = DB::table('ems_registrations as r')
            ->leftJoin('ems_tickets as t', 't.registration_id', '=', 'r.id')
            ->leftJoin('ems_payments as p', 'p.registration_id', '=', 'r.id')
            ->where(function ($q) {
                $q->whereIn('p.status', ['paid', 'completed', 'succeeded'])
                  ->orWhereIn('r.status', ['confirmed', 'paid']);
            })
            ->whereNull('t.id')
            ->count();

        return $this->makeCheckResult(
            'EMS',
            'paid_registrations_without_tickets',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} paid registration(s) missing active ticket issuance." : 'All paid registrations have issued tickets.'
        );
    }

    private function checkEmsDuplicateQrCodes(): array
    {
        if (! Schema::hasTable('ems_tickets')) {
            return $this->makeCheckResult('EMS', 'duplicate_qr_codes', 'PASSED', 0, 'ems_tickets table not present');
        }

        $duplicates = DB::table('ems_tickets')
            ->select('qr_code', DB::raw('count(*) as count'))
            ->whereNotNull('qr_code')
            ->groupBy('qr_code')
            ->having('count', '>', 1)
            ->get();

        $count = $duplicates->count();

        return $this->makeCheckResult(
            'EMS',
            'duplicate_qr_codes',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} duplicate QR code(s) across active tickets." : 'All active ticket QR codes are unique.'
        );
    }

    private function checkEmsConfirmedPaymentFailedMismatch(): array
    {
        if (! Schema::hasTable('ems_registrations') || ! Schema::hasTable('ems_payments')) {
            return $this->makeCheckResult('EMS', 'confirmed_payment_failed_mismatch', 'PASSED', 0, 'EMS tables not present');
        }

        $count = DB::table('ems_registrations as r')
            ->join('ems_payments as p', 'p.registration_id', '=', 'r.id')
            ->whereIn('r.status', ['confirmed', 'CONFIRMED'])
            ->whereIn('p.status', ['failed', 'FAILED'])
            ->count();

        return $this->makeCheckResult(
            'EMS',
            'confirmed_payment_failed_mismatch',
            $count > 0 ? 'WARNING' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} registration(s) marked confirmed with failed payment state." : 'No confirmed registrations with failed payment state.'
        );
    }

    // ----------------------------------------------------
    // DONATIONS INTEGRITY CHECKS
    // ----------------------------------------------------

    private function checkDonationsMissingTransactionReference(): array
    {
        if (! Schema::hasTable('donations')) {
            return $this->makeCheckResult('Donations', 'completed_donations_missing_reference', 'PASSED', 0, 'donations table not present');
        }

        $count = DB::table('donations')
            ->whereIn('status', ['completed', 'COMPLETED', 'paid'])
            ->whereNull('square_checkout_id')
            ->whereNull('transaction_id')
            ->count();

        return $this->makeCheckResult(
            'Donations',
            'completed_donations_missing_reference',
            $count > 0 ? 'WARNING' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} completed donation(s) missing transaction/checkout ID." : 'All completed donations have valid payment transaction references.'
        );
    }

    private function checkDonationsInvalidAmounts(): array
    {
        if (! Schema::hasTable('donations')) {
            return $this->makeCheckResult('Donations', 'invalid_donation_amounts', 'PASSED', 0, 'donations table not present');
        }

        $count = DB::table('donations')
            ->where('amount_cents', '<=', 0)
            ->count();

        return $this->makeCheckResult(
            'Donations',
            'invalid_donation_amounts',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} donation record(s) with invalid non-positive amount." : 'All donation records have positive amounts.'
        );
    }

    // ----------------------------------------------------
    // STORE INTEGRITY CHECKS
    // ----------------------------------------------------

    private function checkStorePaidOrdersMissingFulfillmentStatus(): array
    {
        if (! Schema::hasTable('store_orders')) {
            return $this->makeCheckResult('Store', 'paid_orders_missing_fulfillment_status', 'PASSED', 0, 'store_orders table not present');
        }

        $count = DB::table('store_orders')
            ->whereIn('payment_status', ['paid', 'PAID'])
            ->whereNull('fulfillment_status')
            ->count();

        return $this->makeCheckResult(
            'Store',
            'paid_orders_missing_fulfillment_status',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} paid store order(s) with null fulfillment status." : 'All paid store orders have defined fulfillment status.'
        );
    }

    private function checkStoreNegativeInventory(): array
    {
        if (! Schema::hasTable('store_products')) {
            return $this->makeCheckResult('Store', 'negative_stock_quantity', 'PASSED', 0, 'store_products table not present');
        }

        $count = DB::table('store_products')
            ->where('stock_quantity', '<', 0)
            ->count();

        return $this->makeCheckResult(
            'Store',
            'negative_stock_quantity',
            $count > 0 ? 'WARNING' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} product(s) with negative stock inventory level." : 'All store products have non-negative inventory levels.'
        );
    }

    // ----------------------------------------------------
    // MLIBMS INTEGRITY CHECKS
    // ----------------------------------------------------

    private function checkMlibmsLoansMissingBooks(): array
    {
        if (! Schema::hasTable('mlibms_loans') || ! Schema::hasTable('mlibms_copies')) {
            return $this->makeCheckResult('MLibMS', 'loans_referencing_missing_books', 'PASSED', 0, 'MLibMS tables not present');
        }

        $count = DB::table('mlibms_loans as l')
            ->leftJoin('mlibms_copies as c', 'c.id', '=', 'l.copy_id')
            ->leftJoin('mlibms_books as b', 'b.id', '=', 'c.book_id')
            ->whereIn('l.status', ['active', 'borrowed', 'overdue'])
            ->whereNull('b.id')
            ->count();

        return $this->makeCheckResult(
            'MLibMS',
            'loans_referencing_missing_books',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} active loan(s) referencing non-existent book records." : 'All active loans reference valid books.'
        );
    }

    private function checkMlibmsImpossibleReturnState(): array
    {
        if (! Schema::hasTable('mlibms_loans')) {
            return $this->makeCheckResult('MLibMS', 'impossible_loan_return_state', 'PASSED', 0, 'mlibms_loans table not present');
        }

        $count = DB::table('mlibms_loans')
            ->whereIn('status', ['active', 'borrowed'])
            ->whereNotNull('returned_at')
            ->count();

        return $this->makeCheckResult(
            'MLibMS',
            'impossible_loan_return_state',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} loan(s) marked active with returned_at set." : 'All active loans have consistent return timestamps.'
        );
    }

    // ----------------------------------------------------
    // OPERATIONS INTEGRITY CHECKS
    // ----------------------------------------------------

    private function checkOperationsExecutionsMissingAlerts(): array
    {
        if (! Schema::hasTable('operational_action_executions')) {
            return $this->makeCheckResult('Operations', 'executions_missing_alert_references', 'PASSED', 0, 'operational_action_executions table not present');
        }

        $count = DB::table('operational_action_executions as e')
            ->leftJoin('operational_alerts as a', 'a.id', '=', 'e.operational_alert_id')
            ->whereNull('a.id')
            ->count();

        return $this->makeCheckResult(
            'Operations',
            'executions_missing_alert_references',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} remediation execution(s) referencing missing alert records." : 'All remediation executions reference valid operational alerts.'
        );
    }

    private function checkOperationsApprovalsMissingAlerts(): array
    {
        if (! Schema::hasTable('operational_action_approvals')) {
            return $this->makeCheckResult('Operations', 'approvals_missing_alert_references', 'PASSED', 0, 'operational_action_approvals table not present');
        }

        $count = DB::table('operational_action_approvals as app')
            ->leftJoin('operational_alerts as a', 'a.id', '=', 'app.operational_alert_id')
            ->whereNull('a.id')
            ->count();

        return $this->makeCheckResult(
            'Operations',
            'approvals_missing_alert_references',
            $count > 0 ? 'FAILED' : 'PASSED',
            $count,
            $count > 0 ? "Found {$count} governance approval request(s) referencing missing alert records." : 'All governance approval requests reference valid operational alerts.'
        );
    }

    private function makeCheckResult(string $domain, string $checkKey, string $status, int $issueCount, string $summary): array
    {
        return [
            'domain' => $domain,
            'check_key' => $checkKey,
            'status' => $status,
            'issue_count' => $issueCount,
            'summary' => $summary,
        ];
    }
}
