<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_books' => Book::count(),
            'total_copies' => Copy::count(),
            'available_copies' => Copy::where('status', 'available')->count(),
            'checked_out_copies' => Copy::where('status', 'checked_out')->count(),
            'reserved_copies' => Copy::where('status', 'reserved')->count(),
            'lost_damaged_copies' => Copy::whereIn('status', ['lost', 'damaged'])->count(),
            'active_loans' => Loan::where('status', 'active')->count(),
            'overdue_loans' => Loan::where('status', 'overdue')->count(),
            'active_holds' => Reservation::whereIn('status', ['pending', 'ready_for_pickup'])->count(),
            'total_members' => Member::count(),
            'suspended_members' => Member::where('status', 'suspended')->count(),
        ]);
    }

    /**
     * Sanitized CSV Export of circulation loans.
     */
    public function exportLoans(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="mlibms_circulation_report_' . date('Y_m_d') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Loan UUID', 'Book Title', 'Barcode', 'Member Name', 'Member Card', 'Checked Out At', 'Due At', 'Returned At', 'Status']);

            Loan::with(['copy.book', 'member'])->chunk(100, function ($loans) use ($handle) {
                foreach ($loans as $loan) {
                    fputcsv($handle, [
                        $this->sanitizeCsvField($loan->uuid),
                        $this->sanitizeCsvField($loan->copy->book->title ?? ''),
                        $this->sanitizeCsvField($loan->copy->barcode ?? ''),
                        $this->sanitizeCsvField($loan->member->name ?? ''),
                        $this->sanitizeCsvField($loan->member->library_card_number ?? ''),
                        $loan->checked_out_at?->toDateTimeString() ?? '',
                        $loan->due_at?->toDateTimeString() ?? '',
                        $loan->returned_at?->toDateTimeString() ?? '',
                        $loan->status?->value ?? (string) $loan->status,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    protected function sanitizeCsvField(?string $value): string
    {
        if (is_null($value)) return '';
        $clean = trim($value);
        if (in_array(substr($clean, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $clean;
        }
        return $clean;
    }
}
