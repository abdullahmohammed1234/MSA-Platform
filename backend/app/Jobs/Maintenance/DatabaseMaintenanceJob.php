<?php

namespace App\Jobs\Maintenance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatabaseMaintenanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->queue = 'low';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting database maintenance and disk cleanup...");

        // 1. Database Optimization
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('VACUUM');
                Log::info("Ran VACUUM command on SQLite database.");
            } elseif ($driver === 'mysql') {
                // OPTIMIZE tables in MySQL
                $tables = DB::select('SHOW TABLES');
                $dbName = DB::connection()->getDatabaseName();
                $key = "Tables_in_" . $dbName;
                
                foreach ($tables as $table) {
                    $tableName = $table->$key;
                    DB::statement("OPTIMIZE TABLE `{$tableName}`");
                }
                Log::info("Ran OPTIMIZE command on all MySQL tables.");
            }
        } catch (\Exception $e) {
            Log::warning("Database optimization failed: " . $e->getMessage());
        }

        // 2. Clean temporary PDF reports older than 30 days
        try {
            $reportsPath = storage_path('app/reports');
            if (file_exists($reportsPath)) {
                $files = glob($reportsPath . '/*.pdf');
                $deletedCount = 0;
                
                foreach ($files as $file) {
                    if (time() - filemtime($file) > 30 * 86400) { // 30 days
                        unlink($file);
                        $deletedCount++;
                    }
                }
                Log::info("Cleaned up {$deletedCount} expired analytics reports from disk.");
            }
        } catch (\Exception $e) {
            Log::warning("Disk report cleanup failed: " . $e->getMessage());
        }

        // 3. Clean performance metrics older than 7 days
        try {
            $deletedMetrics = DB::table('performance_metrics')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
            Log::info("Pruned {$deletedMetrics} performance metric records older than 7 days.");
        } catch (\Exception $e) {
            Log::warning("Performance metrics pruning failed: " . $e->getMessage());
        }
    }
}
