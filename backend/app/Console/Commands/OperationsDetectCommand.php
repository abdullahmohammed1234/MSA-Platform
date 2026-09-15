<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalDetectionEngine;
use Illuminate\Console\Command;

class OperationsDetectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'operations:detect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate operational rules across all platform subsystems and trigger deduplicated actionable alerts';

    /**
     * Execute the console command.
     */
    public function handle(OperationalDetectionEngine $engine): int
    {
        $startTime = microtime(true);
        $report = $engine->runAll();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $total = $report['total_detected'];
        $errorCount = count($report['errors']);

        if ($errorCount > 0) {
            $this->warn("Operational rule detection finished with {$errorCount} rule failure(s) in {$durationMs}ms. {$total} issue(s) evaluated.");
        } else {
            $this->info("Operational rule detection completed successfully in {$durationMs}ms. {$total} issue(s) evaluated.");
        }

        return Command::SUCCESS;
    }
}
