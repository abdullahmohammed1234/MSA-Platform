<?php

namespace App\Jobs\Email;

use App\Models\User;
use App\Models\Course;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCourseCompletionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected User $user;
    protected Course $course;
    protected ?string $completionDate;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, Course $course, ?string $completionDate = null)
    {
        $this->user = $user;
        $this->course = $course;
        $this->completionDate = $completionDate;
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending course completion email to user: {$this->user->email} for course: {$this->course->title}");
        
        // Use notifyNow to send synchronously inside the queue worker thread (avoids double queuing)
        $this->user->notifyNow(new CourseCompletedNotification($this->course->title, $this->completionDate));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to send course completion email to {$this->user->email}: " . $exception->getMessage());
    }
}
