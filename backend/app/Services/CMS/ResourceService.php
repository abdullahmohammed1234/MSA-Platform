<?php

namespace App\Services\CMS;

use App\Models\CMS\Resource;
use App\Repositories\CMS\ResourceRepository;
use Illuminate\Support\Str;

class ResourceService
{
    protected $repository;
    protected $revisionService;

    public function __construct(ResourceRepository $repository, RevisionService $revisionService)
    {
        $this->repository = $repository;
        $this->revisionService = $revisionService;
    }

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->repository->list($filters, $perPage);
    }

    public function getPublished()
    {
        return $this->repository->getPublished();
    }

    public function findByUuid(string $uuid): ?Resource
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data, ?int $userId): Resource
    {
        $data['uuid'] = (string) Str::uuid();
        $data['is_external'] = isset($data['is_external']) ? (bool) $data['is_external'] : false;

        $resource = $this->repository->create($data);

        // Save revision
        $this->revisionService->createRevision($resource, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'create_resource', $resource, "Created resource: {$resource->title}");

        return $resource;
    }

    public function update(Resource $resource, array $data, ?int $userId): Resource
    {
        if (isset($data['is_external'])) {
            $data['is_external'] = (bool) $data['is_external'];
        }

        $this->repository->update($resource, $data);

        // Save revision
        $this->revisionService->createRevision($resource, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'update_resource', $resource, "Updated resource: {$resource->title}");

        return $resource;
    }

    public function delete(Resource $resource, ?int $userId): bool
    {
        $title = $resource->title;
        $deleted = $this->repository->delete($resource);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_resource', $resource, "Deleted resource: {$title}");
        }

        return $deleted;
    }

    public function rollback(Resource $resource, int $version, ?int $userId): bool
    {
        return $this->revisionService->rollback($resource, $version, $userId);
    }

    public function getRevisions(Resource $resource)
    {
        return $this->revisionService->getRevisions($resource);
    }
}
