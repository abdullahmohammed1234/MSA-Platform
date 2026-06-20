<?php

namespace App\Services;

use App\Models\Module;
use App\Repositories\ModuleRepository;
use Illuminate\Database\Eloquent\Collection;

class ModuleManagementService
{
    protected $moduleRepository;

    public function __construct(ModuleRepository $moduleRepository)
    {
        $this->moduleRepository = $moduleRepository;
    }

    public function getModulesByCourse(int $courseId): Collection
    {
        return $this->moduleRepository->getByCourse($courseId);
    }

    public function getModule(int $id): ?Module
    {
        return $this->moduleRepository->find($id);
    }

    public function createModule(array $data): Module
    {
        return $this->moduleRepository->create($data);
    }

    public function updateModule(Module $module, array $data): bool
    {
        return $this->moduleRepository->update($module, $data);
    }

    public function deleteModule(Module $module): bool
    {
        return $this->moduleRepository->delete($module);
    }

    public function reorderModules(int $courseId, array $moduleIds): void
    {
        $this->moduleRepository->reorder($courseId, $moduleIds);
    }
}
