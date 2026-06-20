<?php

namespace App\Jobs\Maintenance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationCleanupJob implements ShouldQueue
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
        Log::info("Starting notification history cleanup...");

        $cutoffDate = now()->subDays(60);

        // 1. Delete standard in-app notifications
        $deletedNotifications = DB::table('notifications')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        // 2. Delete notification logs
        $deletedLogs = 0;
        if (SchemaHasTable('notification_logs')) {
            $deletedLogs = DB::table('notification_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
        }

        Log::info("Notification cleanup completed: deleted {$deletedNotifications} notifications and {$deletedLogs} notification logs.");
    }
}

// Inline helper to prevent SQL errors if table doesn't exist
if (!function_exists('SchemaHasTable')) {
    function SchemaHasTable($table) {
        return \Illuminate\Support\Facades\Schema::hasTable($table);
    }
}
