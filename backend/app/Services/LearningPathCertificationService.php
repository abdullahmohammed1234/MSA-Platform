<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Models\User;
use App\Models\Certificate;

class LearningPathCertificationService
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Check if a user completes the entire learning path, and if so, issue the path certificate.
     */
    public function checkPathCompletion(int $userId, int $learningPathId): bool
    {
        // 1. Find the certificate definition for the learning path
        $certificate = Certificate::where('type', 'learning_path')
            ->where('learning_path_id', $learningPathId)
            ->first();

        if (!$certificate) {
            return false;
        }

        // 2. Check if they already have the award
        $existing = \App\Models\CertificateAward::where('user_id', $userId)
            ->where('certificate_id', $certificate->id)
            ->exists();

        if ($existing) {
            return true;
        }

        // 3. Evaluate eligibility
        if ($this->certificateService->checkEligibility($userId, $certificate->id)) {
            $this->certificateService->issueLearningPathCertificate($userId, $learningPathId);
            return true;
        }

        return false;
    }
}
