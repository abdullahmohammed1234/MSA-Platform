<?php

namespace App\Repositories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;

class ModuleRepository
{
    public function find(int $id): ?Module
    {
        return Module::with(['lessons'])->find($id);
    }

    public function getByCourse(int $courseId): Collection
    {
        return Module::with(['lessons'])
            ->where('course_id', $courseId)
            ->orderBy('order', 'asc')
            ->get();
    }

    public function create(array $data): Module
    {
        // Auto-assign order if not provided
        if (!isset($data['order'])) {
            $data['order'] = Module::where('course_id', $data['course_id'])->count() + 1;
        }

        return Module::create($data);
    }

    public function update(Module $module, array $data): bool
    {
        return $module->update($data);
    }

    public function delete(Module $module): ?bool
    {
        return $module->delete();
    }

    public function reorder(int $courseId, array $moduleIds): void
    {
        foreach ($moduleIds as $index => $id) {
            Module::where('id', $id)
                ->where('course_id', $courseId)
                ->update(['order' => $index + 1]);
        }
    }
}
