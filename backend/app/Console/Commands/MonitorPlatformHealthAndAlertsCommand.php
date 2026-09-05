<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformAlertService;
use Illuminate\Console\Command;

class MonitorPlatformHealthAndAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:monitor-health-and-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate system health, update cron heartbeat, and trigger deduplicated platform alerts';

    /**
     * Execute the console command.
     */
    public function handle(PlatformAlertService $alertService): int
    {
        $alerts = $alertService->evaluateAndTriggerAlerts();

        $count = count($alerts);
        if ($count > 0) {
            $this->info("Platform health monitoring completed: {$count} new alert(s) generated.");
        } else {
            $this->info("Platform health monitoring completed: system nominal.");
        }

        return Command::SUCCESS;
    }
}
