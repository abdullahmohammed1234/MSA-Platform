<?php

namespace App\Repositories;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Collection;

class CertificateRepository
{
    public function find(int $id): ?Certificate
    {
        return Certificate::with(['template', 'course', 'learningPath', 'requirements'])->find($id);
    }

    public function findByUuid(string $uuid): ?Certificate
    {
        return Certificate::with(['template', 'course', 'learningPath', 'requirements'])->where('uuid', $uuid)->first();
    }

    public function getByCourse(int $courseId): ?Certificate
    {
        return Certificate::with(['template', 'requirements'])
            ->where('course_id', $courseId)
            ->where('type', 'course')
            ->first();
    }

    public function getByLearningPath(int $learningPathId): ?Certificate
    {
        return Certificate::with(['template', 'requirements'])
            ->where('learning_path_id', $learningPathId)
            ->where('type', 'learning_path')
            ->first();
    }

    public function getAll(): Collection
    {
        return Certificate::with(['template', 'course', 'learningPath'])->get();
    }

    public function create(array $data): Certificate
    {
        return Certificate::create($data);
    }

    public function update(Certificate $certificate, array $data): bool
    {
        return $certificate->update($data);
    }

    public function delete(Certificate $certificate): ?bool
    {
        return $certificate->delete();
    }

    // --- Template queries ---

    public function findTemplate(int $id): ?CertificateTemplate
    {
        return CertificateTemplate::find($id);
    }

    public function findTemplateByUuid(string $uuid): ?CertificateTemplate
    {
        return CertificateTemplate::where('uuid', $uuid)->first();
    }

    public function getAllTemplates(): Collection
    {
        return CertificateTemplate::all();
    }

    public function createTemplate(array $data): CertificateTemplate
    {
        return CertificateTemplate::create($data);
    }

    public function updateTemplate(CertificateTemplate $template, array $data): bool
    {
        return $template->update($data);
    }

    public function deleteTemplate(CertificateTemplate $template): ?bool
    {
        return $template->delete();
    }
}
