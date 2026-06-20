<?php

namespace App\Jobs\Maintenance;

use App\Models\JobLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArchiveOldLogsJob implements ShouldQueue
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
        Log::info("Starting job logs archival process...");

        $cutoff = now()->subDays(30);
        
        $query = JobLog::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count > 0) {
            // Archive in chunks to save memory
            $archiveFile = 'archives/job_logs_archive_' . now()->format('Y_m_d_His') . '.jsonl';
            $fp = fopen('php://temp', 'r+');
            
            $query->chunk(500, function ($logs) use ($fp) {
                foreach ($logs as $log) {
                    fwrite($fp, json_encode($log->toArray()) . "\n");
                }
            });
            
            rewind($fp);
            Storage::put($archiveFile, $fp);
            fclose($fp);
            
            // Delete archived records
            $deleted = $query->delete();
            
            Log::info("Archived {$deleted} job execution logs to storage file: {$archiveFile}");
        } else {
            Log::info("No job logs older than 30 days to archive.");
        }
    }
}
