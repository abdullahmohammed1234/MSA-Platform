<?php

namespace App\Jobs\Certificates;

use App\Models\User;
use App\Models\Course;
use App\Models\Certificate;
use App\Models\CertificateAward;
use App\Models\CertificateTemplate;
use App\Services\CertificateGenerationService;
use App\Services\AchievementService;
use App\Services\BadgeService;
use App\Notifications\CertificateEarnedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class GenerateCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [15, 45, 90, 180];

    protected User $user;
    protected Course $course;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(CertificateGenerationService $generator): void
    {
        Log::info("Asynchronously generating certificate for user: {$this->user->email} for course: {$this->course->title}");

        // 1. Get or create certificate definition for the course
        $certificate = Certificate::where('course_id', $this->course->id)->first();
        if (!$certificate) {
            $template = CertificateTemplate::first();
            if (!$template) {
                $template = CertificateTemplate::create([
                    'name' => 'Default Course Template',
                    'title_template' => 'Certificate of Completion',
                    'description_template' => 'For successfully completing [course_title]',
                    'layout' => 'landscape',
                    'branding' => ['primary_color' => '#0F172A', 'secondary_color' => '#10B981'],
                    'status' => 'active',
                ]);
            }

            $certificate = Certificate::create([
                'certificate_template_id' => $template->id,
                'title' => "Certificate of Completion - " . $this->course->title,
                'description' => "Awarded for successfully completing the course: " . $this->course->title,
                'type' => 'course',
                'course_id' => $this->course->id,
            ]);

            // Add default requirements
            $certificate->requirements()->create([
                'type' => 'lesson_completion',
                'parameters' => ['percentage' => 100],
            ]);
            $certificate->requirements()->create([
                'type' => 'passing_score',
                'parameters' => [],
            ]);
        }

        // 2. Prevent duplicate awards
        $award = CertificateAward::where('user_id', $this->user->id)
            ->where('certificate_id', $certificate->id)
            ->first();

        if (!$award) {
            // Create award record
            $award = CertificateAward::create([
                'user_id' => $this->user->id,
                'certificate_id' => $certificate->id,
                'title' => $certificate->title,
                'description' => $certificate->description,
                'issued_at' => now(),
            ]);
        }

        // 3. Generate PDF and Save File
        Log::info("Generating PDF for award ID: {$award->id}");
        $pdfPath = $generator->generatePdf($award);

        // 4. Notify User (dispatched to high-priority queue or sent immediately)
        Log::info("Notifying user {$this->user->email} of certificate earned.");
        $this->user->notify(new CertificateEarnedNotification($award));

        // 5. Evaluate achievements and badges
        app(AchievementService::class)->evaluateAchievements($this->user->id, 'certificate_earned', ['course_id' => $this->course->id]);
        app(BadgeService::class)->evaluateBadges($this->user->id, 'certificate_earned', ['course_id' => $this->course->id]);

        Log::info("Certificate generation job completed successfully for award ID: {$award->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to generate certificate for user {$this->user->email} for course {$this->course->title}: " . $exception->getMessage());
    }
}
