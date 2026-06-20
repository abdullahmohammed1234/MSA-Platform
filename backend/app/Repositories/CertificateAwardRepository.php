<?php

namespace App\Repositories;

use App\Models\CertificateAward;
use Illuminate\Database\Eloquent\Collection;

class CertificateAwardRepository
{
    public function find(int $id): ?CertificateAward
    {
        return CertificateAward::with(['user', 'certificate.course', 'certificate.learningPath', 'issuer'])->find($id);
    }

    public function findByUuid(string $uuid): ?CertificateAward
    {
        return CertificateAward::with(['user', 'certificate.course', 'certificate.learningPath', 'issuer'])->where('uuid', $uuid)->first();
    }

    public function findByCode(string $code): ?CertificateAward
    {
        return CertificateAward::with(['user', 'certificate.course', 'certificate.learningPath', 'issuer'])->where('code', $code)->first();
    }

    public function findByToken(string $token): ?CertificateAward
    {
        return CertificateAward::with(['user', 'certificate.course', 'certificate.learningPath', 'issuer'])->where('verification_token', $token)->first();
    }

    public function getByUser(int $userId): Collection
    {
        return CertificateAward::with(['certificate.course', 'certificate.learningPath', 'user'])
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function create(array $data): CertificateAward
    {
        return CertificateAward::create($data);
    }

    public function hasAward(int $userId, int $certificateId): bool
    {
        return CertificateAward::where('user_id', $userId)
            ->where('certificate_id', $certificateId)
            ->exists();
    }

    public function hasCourseAward(int $userId, int $courseId): bool
    {
        return CertificateAward::where('user_id', $userId)
            ->whereHas('certificate', function ($query) use ($courseId) {
                $query->where('course_id', $courseId)->where('type', 'course');
            })
            ->exists();
    }

    public function getCourseAward(int $userId, int $courseId): ?CertificateAward
    {
        return CertificateAward::where('user_id', $userId)
            ->whereHas('certificate', function ($query) use ($courseId) {
                $query->where('course_id', $courseId)->where('type', 'course');
            })
            ->first();
    }

    public function hasLearningPathAward(int $userId, int $learningPathId): bool
    {
        return CertificateAward::where('user_id', $userId)
            ->whereHas('certificate', function ($query) use ($learningPathId) {
                $query->where('learning_path_id', $learningPathId)->where('type', 'learning_path');
            })
            ->exists();
    }

    public function getAllAwards(): Collection
    {
        return CertificateAward::with(['user', 'certificate', 'issuer'])->latest()->get();
    }
}
