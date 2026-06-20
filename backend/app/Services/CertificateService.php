<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateAward;
use App\Models\CertificateTemplate;
use App\Models\CertificateVerification;
use App\Models\User;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateAwardRepository;
use App\Repositories\CourseRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CertificateService
{
    protected $certificateRepository;
    protected $certificateAwardRepository;
    protected $courseRepository;
    protected $requirementEngine;

    public function __construct(
        CertificateRepository $certificateRepository,
        CertificateAwardRepository $certificateAwardRepository,
        CourseRepository $courseRepository,
        CertificateRequirementEngine $requirementEngine
    ) {
        $this->certificateRepository = $certificateRepository;
        $this->certificateAwardRepository = $certificateAwardRepository;
        $this->courseRepository = $courseRepository;
        $this->requirementEngine = $requirementEngine;
    }

    /**
     * Check if a user meets all requirements to be issued a specific certificate.
     */
    public function checkEligibility(int $userId, int $certificateId): bool
    {
        $user = User::find($userId);
        $certificate = $this->certificateRepository->find($certificateId);

        if (!$user || !$certificate) {
            return false;
        }

        foreach ($certificate->requirements as $req) {
            if (!$this->requirementEngine->evaluateRequirement($user, $req)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Issue a course certificate award. Matches original signature for backward compatibility.
     */
    public function issueCertificate(int $userId, int $courseId): CertificateAward
    {
        $course = $this->courseRepository->find($courseId);
        if (!$course) {
            throw new Exception("Course not found.");
        }

        // 1. Check if the course has a certificate definition
        $certificate = $this->certificateRepository->getByCourse($courseId);
        if (!$certificate) {
            // Create a default certificate definition and template if none exists yet (graceful fallback)
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

            $certificate = $this->certificateRepository->create([
                'certificate_template_id' => $template->id,
                'title' => "Certificate of Completion - " . $course->title,
                'description' => "Awarded for successfully completing the course: " . $course->title,
                'type' => 'course',
                'course_id' => $courseId,
            ]);

            // Add default requirement: lesson completion
            $certificate->requirements()->create([
                'type' => 'lesson_completion',
                'parameters' => ['percentage' => 100],
            ]);
            // Add default requirement: passing score
            $certificate->requirements()->create([
                'type' => 'passing_score',
                'parameters' => [],
            ]);
        }

        // 2. Avoid duplicates
        $existing = $this->certificateAwardRepository->getCourseAward($userId, $courseId);
        if ($existing) {
            return $existing;
        }

        // 3. Evaluate requirements
        if (!$this->checkEligibility($userId, $certificate->id)) {
            throw new Exception("User is not eligible for this certificate yet.");
        }

        // 4. Create certificate award
        $award = $this->certificateAwardRepository->create([
            'user_id' => $userId,
            'certificate_id' => $certificate->id,
            'title' => $certificate->title,
            'description' => $certificate->description,
            'issued_at' => now(),
        ]);

        // 5. Trigger PDF generation (job dispatches inside Service)
        dispatch(new \App\Jobs\GenerateCertificatePdfJob($award));

        // 6. Trigger notifications and events
        $user = User::find($userId);
        if ($user) {
            $user->notify(new \App\Notifications\CertificateEarnedNotification($award));
            
            // Trigger Badge & Achievement checks
            app(AchievementService::class)->evaluateAchievements($userId, 'certificate_earned', ['course_id' => $courseId]);
            app(BadgeService::class)->evaluateBadges($userId, 'certificate_earned', ['course_id' => $courseId]);
        }

        return $award;
    }

    /**
     * Issue a learning path certificate.
     */
    public function issueLearningPathCertificate(int $userId, int $learningPathId): CertificateAward
    {
        $certificate = $this->certificateRepository->getByLearningPath($learningPathId);
        if (!$certificate) {
            throw new Exception("Certificate definition for this learning path does not exist.");
        }

        $existing = $this->certificateAwardRepository->hasLearningPathAward($userId, $learningPathId);
        if ($existing) {
            return CertificateAward::where('user_id', $userId)
                ->whereHas('certificate', function ($q) use ($learningPathId) {
                    $q->where('learning_path_id', $learningPathId);
                })->first();
        }

        if (!$this->checkEligibility($userId, $certificate->id)) {
            throw new Exception("User has not completed the learning path requirements.");
        }

        $award = $this->certificateAwardRepository->create([
            'user_id' => $userId,
            'certificate_id' => $certificate->id,
            'title' => $certificate->title,
            'description' => $certificate->description,
            'issued_at' => now(),
        ]);

        dispatch(new \App\Jobs\GenerateCertificatePdfJob($award));

        $user = User::find($userId);
        if ($user) {
            $user->notify(new \App\Notifications\CertificateEarnedNotification($award));
            app(AchievementService::class)->evaluateAchievements($userId, 'path_completed', ['learning_path_id' => $learningPathId]);
            app(BadgeService::class)->evaluateBadges($userId, 'path_completed', ['learning_path_id' => $learningPathId]);
        }

        return $award;
    }

    /**
     * Manually award a special recognition certificate (by admins).
     */
    public function issueSpecialCertificate(int $userId, int $certificateId, string $title, ?string $description, int $adminId): CertificateAward
    {
        $certificate = $this->certificateRepository->find($certificateId);
        if (!$certificate) {
            throw new Exception("Certificate definition not found.");
        }

        $award = $this->certificateAwardRepository->create([
            'user_id' => $userId,
            'certificate_id' => $certificate->id,
            'title' => $title,
            'description' => $description,
            'issued_by' => $adminId,
            'issued_at' => now(),
        ]);

        dispatch(new \App\Jobs\GenerateCertificatePdfJob($award));

        $user = User::find($userId);
        if ($user) {
            $user->notify(new \App\Notifications\CertificateEarnedNotification($award));
        }

        return $award;
    }

    /**
     * Verify a certificate and log the verification scan.
     */
    public function verifyCertificate(string $tokenOrCode, ?string $ipAddress = null, ?string $userAgent = null): CertificateAward
    {
        $award = $this->certificateAwardRepository->findByToken($tokenOrCode);
        if (!$award) {
            $award = $this->certificateAwardRepository->findByCode($tokenOrCode);
        }

        if (!$award) {
            throw new Exception("Invalid certificate code or token.");
        }

        CertificateVerification::create([
            'certificate_award_id' => $award->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'verified_at' => now(),
        ]);

        return $award;
    }

    public function getCertificateByUuid(string $uuid): ?CertificateAward
    {
        return $this->certificateAwardRepository->findByUuid($uuid);
    }

    public function getCertificateByCode(string $code): ?CertificateAward
    {
        return $this->certificateAwardRepository->findByCode($code);
    }

    public function getUserCertificates(int $userId): Collection
    {
        return $this->certificateAwardRepository->getByUser($userId);
    }

    public function revokeCertificateAward(int $awardId): bool
    {
        $award = CertificateAward::find($awardId);
        if (!$award) {
            return false;
        }
        return $award->delete();
    }
}
