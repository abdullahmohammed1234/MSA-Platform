<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformAuditService;
use Illuminate\Console\Command;

class PrunePlatformLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:prune-logs {--days=180 : Retention period in days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old audit logs and platform health histories beyond retention period';

    /**
     * Execute the console command.
     */
    public function handle(PlatformAuditService $auditService): int
    {
        $days = max(30, (int) $this->option('days'));

        $this->info("Pruning platform audit logs older than {$days} days...");

        $deletedCount = $auditService->prune($days);

        $this->info("Successfully pruned {$deletedCount} old audit log entries.");

        return Command::SUCCESS;
    }
}
