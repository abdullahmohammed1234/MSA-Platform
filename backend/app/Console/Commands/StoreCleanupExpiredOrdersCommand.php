<?php

namespace App\Console\Commands;

use App\Store\Services\StoreCheckoutService;
use Illuminate\Console\Command;

class StoreCleanupExpiredOrdersCommand extends Command
{
    protected $signature = 'store:cleanup-expired-orders {--minutes=30 : Order expiration threshold in minutes}';

    protected $description = 'Release stock and cancel expired pending Store checkout orders';

    public function handle(StoreCheckoutService $checkoutService): int
    {
        $minutes = (int) $this->option('minutes');

        $this->info("Scanning for pending Store orders older than {$minutes} minutes...");

        $count = $checkoutService->cleanupExpiredOrders($minutes);

        $this->info("Successfully cleaned up {$count} expired Store orders.");

        return Command::SUCCESS;
    }
}
