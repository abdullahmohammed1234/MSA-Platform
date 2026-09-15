<?php

namespace App\Services\Intelligence;

use App\Store\Models\StoreOrder;
use App\Store\Models\StoreProduct;
use Illuminate\Support\Facades\Schema;

class StoreIntelligenceService
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

        if (! Schema::hasTable('store_orders')) {
            return $this->emptyStoreAnalytics();
        }

        $totalCol = Schema::hasColumn('store_orders', 'total_cents') ? 'total_cents' : 'total';

        // Orders KPI
        $currOrders = StoreOrder::whereBetween('created_at', [$cStart, $cEnd])->where('payment_status', 'paid')->count();
        $prevOrders = StoreOrder::whereBetween('created_at', [$pStart, $pEnd])->where('payment_status', 'paid')->count();
        $totalOrders = StoreOrder::where('payment_status', 'paid')->count();

        // Revenue KPI
        $currRevRaw = (float) (StoreOrder::whereBetween('created_at', [$cStart, $cEnd])->where('payment_status', 'paid')->sum($totalCol) ?? 0);
        $prevRevRaw = (float) (StoreOrder::whereBetween('created_at', [$pStart, $pEnd])->where('payment_status', 'paid')->sum($totalCol) ?? 0);

        $currRevenue = $totalCol === 'total_cents' ? $currRevRaw / 100 : $currRevRaw;
        $prevRevenue = $totalCol === 'total_cents' ? $prevRevRaw / 100 : $prevRevRaw;

        // Pending & Unfulfilled Orders
        $pendingOrders = StoreOrder::where('fulfillment_status', 'unfulfilled')->where('payment_status', 'paid')->count();

        // Inventory Warnings (Products with stock <= 5)
        $inventoryWarnings = [];
        $lowStockCount = 0;
        if (Schema::hasTable('store_products')) {
            $lowStockProducts = StoreProduct::where('is_active', true)
                ->where('stock_quantity', '<=', 5)
                ->get(['id', 'name', 'stock_quantity', 'price']);
            $lowStockCount = $lowStockProducts->count();
            $inventoryWarnings = $lowStockProducts;
        }

        // Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $cnt = StoreOrder::whereBetween('created_at', [$dayStart, $dayEnd])->where('payment_status', 'paid')->count();
            $revRaw = (float) (StoreOrder::whereBetween('created_at', [$dayStart, $dayEnd])->where('payment_status', 'paid')->sum($totalCol) ?? 0);
            $rev = $totalCol === 'total_cents' ? $revRaw / 100 : $revRaw;

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'orders' => $cnt,
                'revenue' => round($rev, 2),
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
                'order_count' => [
                    'current' => $currOrders,
                    'previous' => $prevOrders,
                    'total' => $totalOrders,
                    'pct_change' => $this->helper->calculatePctChange($currOrders, $prevOrders),
                ],
                'revenue' => [
                    'current' => round($currRevenue, 2),
                    'previous' => round($prevRevenue, 2),
                    'pct_change' => $this->helper->calculatePctChange($currRevenue, $prevRevenue),
                ],
                'pending_fulfillment' => $pendingOrders,
                'low_stock_count' => $lowStockCount,
            ],
            'inventory_warnings' => $inventoryWarnings,
            'trends' => $trends,
        ];
    }

    private function emptyStoreAnalytics(): array
    {
        return [
            'period' => ['type' => '30d'],
            'kpis' => [
                'order_count' => ['current' => 0, 'previous' => 0, 'total' => 0, 'pct_change' => 0.0],
                'revenue' => ['current' => 0.0, 'previous' => 0.0, 'pct_change' => 0.0],
                'pending_fulfillment' => 0,
                'low_stock_count' => 0,
            ],
            'inventory_warnings' => [],
            'trends' => [],
        ];
    }
}
