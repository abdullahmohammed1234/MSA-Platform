<?php

namespace App\Jobs\Email;

use App\Models\User;
use App\Notifications\UpcomingTrainingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTrainingReminderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected User $user;
    protected string $trainingTitle;
    protected string $trainingDate;
    protected string $trainingLocation;
    protected string $trainingDescription;
    protected string $trainingSlug;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        string $trainingTitle,
        string $trainingDate,
        string $trainingLocation,
        string $trainingDescription = '',
        string $trainingSlug = ''
    ) {
        $this->user = $user;
        $this->trainingTitle = $trainingTitle;
        $this->trainingDate = $trainingDate;
        $this->trainingLocation = $trainingLocation;
        $this->trainingDescription = $trainingDescription;
        $this->trainingSlug = $trainingSlug;
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending training reminder email to user: {$this->user->email} for training: {$this->trainingTitle}");
        
        $this->user->notifyNow(new UpcomingTrainingNotification(
            $this->trainingTitle,
            $this->trainingDate,
            $this->trainingLocation,
            $this->trainingDescription,
            $this->trainingSlug
        ));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to send training reminder email to {$this->user->email}: " . $exception->getMessage());
    }
}
