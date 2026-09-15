<?php

namespace App\Services\Intelligence;

use App\Donations\Models\Donation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DonationIntelligenceService
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

        if (! Schema::hasTable('donations')) {
            return $this->emptyDonationAnalytics();
        }

        $amountCol = Schema::hasColumn('donations', 'amount_cents') ? 'amount_cents' : 'amount';

        // 1. Current vs Previous Counts & Volumes
        $currQuery = Donation::whereBetween('created_at', [$cStart, $cEnd])->whereIn('status', ['paid', 'completed']);
        $prevQuery = Donation::whereBetween('created_at', [$pStart, $pEnd])->whereIn('status', ['paid', 'completed']);

        $currCount = (clone $currQuery)->count();
        $prevCount = (clone $prevQuery)->count();
        $totalCount = Donation::whereIn('status', ['paid', 'completed'])->count();

        $currSumRaw = (float) ((clone $currQuery)->sum($amountCol) ?? 0);
        $prevSumRaw = (float) ((clone $prevQuery)->sum($amountCol) ?? 0);

        $currVolume = $amountCol === 'amount_cents' ? $currSumRaw / 100 : $currSumRaw;
        $prevVolume = $amountCol === 'amount_cents' ? $prevSumRaw / 100 : $prevSumRaw;
        $totalVolumeRaw = (float) (Donation::whereIn('status', ['paid', 'completed'])->sum($amountCol) ?? 0);
        $totalVolume = $amountCol === 'amount_cents' ? $totalVolumeRaw / 100 : $totalVolumeRaw;

        // Average Donation Size
        $avgSize = $currCount > 0 ? round($currVolume / $currCount, 2) : 0.0;

        // Refunds
        $refundsCount = 0;
        if (Schema::hasTable('donation_refunds')) {
            $refundsCount = DB::table('donation_refunds')->whereBetween('created_at', [$cStart, $cEnd])->count();
        }

        // 2. Trend Data
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $cnt = Donation::whereBetween('created_at', [$dayStart, $dayEnd])->whereIn('status', ['paid', 'completed'])->count();
            $sumRaw = (float) (Donation::whereBetween('created_at', [$dayStart, $dayEnd])->whereIn('status', ['paid', 'completed'])->sum($amountCol) ?? 0);
            $vol = $amountCol === 'amount_cents' ? $sumRaw / 100 : $sumRaw;

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'count' => $cnt,
                'volume' => round($vol, 2),
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
                'donation_count' => [
                    'current' => $currCount,
                    'previous' => $prevCount,
                    'total' => $totalCount,
                    'pct_change' => $this->helper->calculatePctChange($currCount, $prevCount),
                ],
                'donation_volume' => [
                    'current' => round($currVolume, 2),
                    'previous' => round($prevVolume, 2),
                    'total' => round($totalVolume, 2),
                    'pct_change' => $this->helper->calculatePctChange($currVolume, $prevVolume),
                ],
                'average_donation_size' => $avgSize,
                'refunds_count' => $refundsCount,
            ],
            'trends' => $trends,
        ];
    }

    private function emptyDonationAnalytics(): array
    {
        return [
            'period' => ['type' => '30d'],
            'kpis' => [
                'donation_count' => ['current' => 0, 'previous' => 0, 'total' => 0, 'pct_change' => 0.0],
                'donation_volume' => ['current' => 0.0, 'previous' => 0.0, 'total' => 0.0, 'pct_change' => 0.0],
                'average_donation_size' => 0.0,
                'refunds_count' => 0,
            ],
            'trends' => [],
        ];
    }
}
