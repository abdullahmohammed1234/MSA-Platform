<?php

namespace App\Jobs\Maintenance;

use App\Models\CertificateVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CertificateVerificationCleanupJob implements ShouldQueue
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
        Log::info("Starting certificate verification logs cleanup...");

        // Delete scan logs older than 180 days
        $deleted = CertificateVerification::where('verified_at', '<', now()->subDays(180))->delete();

        Log::info("Cleaned up {$deleted} old certificate verification records.");
    }
}
