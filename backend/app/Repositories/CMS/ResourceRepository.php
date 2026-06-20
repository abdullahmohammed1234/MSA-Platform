<?php

namespace App\Repositories\CMS;

use App\Models\CMS\Resource;
use Illuminate\Database\Eloquent\Builder;

class ResourceRepository
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Resource::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getPublished()
    {
        return Resource::where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByUuid(string $uuid): ?Resource
    {
        return Resource::where('uuid', $uuid)->first();
    }

    public function create(array $data): Resource
    {
        return Resource::create($data);
    }

    public function update(Resource $resource, array $data): bool
    {
        return $resource->update($data);
    }

    public function delete(Resource $resource): bool
    {
        return $resource->delete();
    }
}
