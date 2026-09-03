<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DmsReportController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasPermissionTo('donations.reports'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Monthly revenue report for current year
        $year = (int) $request->input('year', date('Y'));
        $monthly = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = Carbon::create($year, $month, 1)->endOfMonth();

            $cents = Donation::paid()
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount_cents');

            $count = Donation::paid()
                ->whereBetween('paid_at', [$start, $end])
                ->count();

            $monthly[] = [
                'month' => $start->format('M Y'),
                'month_number' => $month,
                'amount_cents' => (int) $cents,
                'formatted_amount' => '$'.number_format($cents / 100, 2).' CAD',
                'count' => $count,
            ];
        }

        // Breakdown by Status
        $byStatus = [
            'paid' => Donation::paid()->count(),
            'pending' => Donation::pending()->count(),
            'refunded' => Donation::where('status', DonationStatus::Refunded->value)->count(),
            'failed' => Donation::where('status', DonationStatus::Failed->value)->count(),
        ];

        return response()->json([
            'success' => true,
            'year' => $year,
            'monthly_reports' => $monthly,
            'status_breakdown' => $byStatus,
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasPermissionTo('donations.export'))) {
            return response()->json(['message' => 'Unauthorized for CSV export.'], 403);
        }

        $donations = Donation::query()->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="donations_export_'.date('Ymd_His').'.csv"',
        ];

        $callback = function () use ($donations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Donation Number',
                'Donor Name',
                'Donor Email',
                'Amount (CAD)',
                'Status',
                'Anonymous',
                'Dedication',
                'Square Order ID',
                'Square Payment ID',
                'Paid At',
                'Created At',
            ]);

            foreach ($donations as $d) {
                $name = $d->is_anonymous ? 'Anonymous' : $d->donor_name;
                $email = $d->is_anonymous ? '***@***.***' : $d->donor_email;

                fputcsv($file, [
                    $this->escapeCsvFormula($d->donation_number),
                    $this->escapeCsvFormula($name),
                    $this->escapeCsvFormula($email),
                    number_format($d->amount_cents / 100, 2),
                    $d->status->value,
                    $d->is_anonymous ? 'Yes' : 'No',
                    $this->escapeCsvFormula($d->dedication ?? ''),
                    $this->escapeCsvFormula($d->square_order_id ?? ''),
                    $this->escapeCsvFormula($d->square_payment_id ?? ''),
                    $d->paid_at ? $d->paid_at->toIso8601String() : '',
                    $d->created_at->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function escapeCsvFormula(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
