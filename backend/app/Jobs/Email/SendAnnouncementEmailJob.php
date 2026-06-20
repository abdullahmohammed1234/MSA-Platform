<?php

namespace App\Jobs\Email;

use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAnnouncementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected User $user;
    protected string $title;
    protected string $excerpt;
    protected string $slug;
    protected string $audience;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $title, string $excerpt, string $slug, string $audience = 'All')
    {
        $this->user = $user;
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->slug = $slug;
        $this->audience = $audience;
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending announcement email to user: {$this->user->email} for announcement: {$this->title}");
        
        $this->user->notifyNow(new NewAnnouncementNotification(
            $this->title,
            $this->excerpt,
            $this->slug,
            $this->audience
        ));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to send announcement email to {$this->user->email}: " . $exception->getMessage());
    }
}
