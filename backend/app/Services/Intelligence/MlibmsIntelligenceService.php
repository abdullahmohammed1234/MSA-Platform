<?php

namespace App\Services\Intelligence;

use App\Mlibms\Models\Book as MlibmsBook;
use App\Mlibms\Models\Copy as MlibmsCopy;
use App\Mlibms\Models\Loan as MlibmsLoan;
use Illuminate\Support\Facades\Schema;

class MlibmsIntelligenceService
{
    private EmsIntelligenceService $helper;

    public function __construct(EmsIntelligenceService $helper)
    {
        $this->helper = $helper;
    }

    public function getAnalytics(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $bounds = $this->helper->resolveDateBounds($period, $startDate, $endDate);
        $cStart = $bounds['current']['start'];
        $cEnd = $bounds['current']['end'];
        $pStart = $bounds['previous']['start'];
        $pEnd = $bounds['previous']['end'];

        if (! Schema::hasTable('mlibms_books')) {
            return $this->emptyMlibmsAnalytics();
        }

        // Total Books & Available Copies
        $totalBooks = MlibmsBook::count();
        $totalCopies = Schema::hasTable('mlibms_copies') ? MlibmsCopy::count() : 0;
        $availableCopies = Schema::hasTable('mlibms_copies') ? MlibmsCopy::where('status', 'available')->count() : 0;

        // Loans KPIs
        $currLoans = MlibmsLoan::whereBetween('created_at', [$cStart, $cEnd])->count();
        $prevLoans = MlibmsLoan::whereBetween('created_at', [$pStart, $pEnd])->count();
        $activeLoans = MlibmsLoan::whereNull('returned_at')->count();
        $overdueLoans = MlibmsLoan::whereNull('returned_at')->where('due_at', '<', now())->count();
        $returnedLoans = MlibmsLoan::whereBetween('returned_at', [$cStart, $cEnd])->count();

        // Books added in period
        $booksAdded = MlibmsBook::whereBetween('created_at', [$cStart, $cEnd])->count();

        // Inventory Utilization Rate (% of copies currently on loan)
        $utilizationRate = $totalCopies > 0 ? round((($totalCopies - $availableCopies) / $totalCopies) * 100, 1) : 0.0;

        // Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $cnt = MlibmsLoan::whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $ret = MlibmsLoan::whereBetween('returned_at', [$dayStart, $dayEnd])->count();

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'new_loans' => $cnt,
                'returns' => $ret,
            ];

            $cursor->addDay();
        }

        return [
            'period' => [
                'type' => $period,
                'current_start' => $cStart->toIso8601String(),
                'current_end' => $cEnd->toIso8601String(),
            ],
            'kpis' => [
                'total_books' => $totalBooks,
                'total_copies' => $totalCopies,
                'available_copies' => $availableCopies,
                'active_loans' => $activeLoans,
                'overdue_loans' => $overdueLoans,
                'returned_loans' => $returnedLoans,
                'books_added' => $booksAdded,
                'utilization_rate' => $utilizationRate,
                'period_loans' => [
                    'current' => $currLoans,
                    'previous' => $prevLoans,
                    'pct_change' => $this->helper->calculatePctChange($currLoans, $prevLoans),
                ],
            ],
            'trends' => $trends,
        ];
    }

    private function emptyMlibmsAnalytics(): array
    {
        return [
            'period' => ['type' => '30d'],
            'kpis' => [
                'total_books' => 0,
                'total_copies' => 0,
                'available_copies' => 0,
                'active_loans' => 0,
                'overdue_loans' => 0,
                'returned_loans' => 0,
                'books_added' => 0,
                'utilization_rate' => 0.0,
                'period_loans' => ['current' => 0, 'previous' => 0, 'pct_change' => 0.0],
            ],
            'trends' => [],
        ];
    }
}
