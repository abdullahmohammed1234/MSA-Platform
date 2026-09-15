<?php

namespace App\Services\Operations\Rules;

use App\Mlibms\Models\Loan;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Schema;

class MlibmsOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('mlibms_loans')) {
            return 0;
        }

        $detected = 0;

        // 1. Loans overdue > 30 days (High severity)
        $overdue30Days = Loan::whereNull('returned_at')
            ->where('due_at', '<', now()->subDays(30))
            ->with(['copy.book', 'member'])
            ->get();

        foreach ($overdue30Days as $loan) {
            $bookTitle = $loan->copy?->book?->title ?? 'Unknown Book';
            $daysOverdue = (int) now()->diffInDays($loan->due_at);

            $this->alertService->upsertAlert([
                'category' => 'mlibms',
                'severity' => 'high',
                'title' => "Critical Overdue Loan: {$bookTitle}",
                'description' => "Book copy '{$bookTitle}' (Barcode: {$loan->copy?->barcode}) is {$daysOverdue} days overdue.",
                'source_type' => 'Loan',
                'source_id' => (string) $loan->id,
                'rule_key' => 'mlibms_loan_overdue_30d',
                'action_url' => '/mlibms/loans',
                'metadata' => [
                    'loan_id' => $loan->id,
                    'loan_uuid' => $loan->uuid,
                    'book_title' => $bookTitle,
                    'barcode' => $loan->copy?->barcode,
                    'due_at' => $loan->due_at?->toIso8601String(),
                    'days_overdue' => $daysOverdue,
                ],
            ]);
            $detected++;
        }

        // 2. Loans overdue > 7 days up to 30 days (Medium severity)
        $overdue7Days = Loan::whereNull('returned_at')
            ->where('due_at', '<', now()->subDays(7))
            ->where('due_at', '>=', now()->subDays(30))
            ->with(['copy.book', 'member'])
            ->get();

        foreach ($overdue7Days as $loan) {
            $bookTitle = $loan->copy?->book?->title ?? 'Unknown Book';
            $daysOverdue = (int) now()->diffInDays($loan->due_at);

            $this->alertService->upsertAlert([
                'category' => 'mlibms',
                'severity' => 'medium',
                'title' => "Overdue Loan (> 7 Days): {$bookTitle}",
                'description' => "Book copy '{$bookTitle}' (Barcode: {$loan->copy?->barcode}) is {$daysOverdue} days overdue.",
                'source_type' => 'Loan',
                'source_id' => (string) $loan->id,
                'rule_key' => 'mlibms_loan_overdue_7d',
                'action_url' => '/mlibms/loans',
                'metadata' => [
                    'loan_id' => $loan->id,
                    'loan_uuid' => $loan->uuid,
                    'book_title' => $bookTitle,
                    'barcode' => $loan->copy?->barcode,
                    'due_at' => $loan->due_at?->toIso8601String(),
                    'days_overdue' => $daysOverdue,
                ],
            ]);
            $detected++;
        }

        return $detected;
    }
}
