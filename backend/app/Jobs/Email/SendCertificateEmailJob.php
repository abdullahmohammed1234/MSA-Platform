<?php

namespace App\Jobs\Email;

use App\Models\User;
use App\Models\CertificateAward;
use App\Notifications\CertificateEarnedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCertificateEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected User $user;
    protected CertificateAward $award;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, CertificateAward $award)
    {
        $this->user = $user;
        $this->award = $award;
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending certificate email to user: {$this->user->email} for certificate: {$this->award->title}");
        
        $this->user->notifyNow(new CertificateEarnedNotification($this->award));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to send certificate email to {$this->user->email}: " . $exception->getMessage());
    }
}
