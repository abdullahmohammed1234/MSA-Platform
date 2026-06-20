<?php

namespace App\Services\CMS;

use App\Models\CMS\Event;
use App\Repositories\CMS\EventRepository;
use Illuminate\Support\Str;

class EventService
{
    protected $repository;
    protected $revisionService;

    public function __construct(EventRepository $repository, RevisionService $revisionService)
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

    public function findByUuid(string $uuid): ?Event
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data, ?int $userId): Event
    {
        $data['uuid'] = (string) Str::uuid();

        // Ensure defaults
        $data['featured'] = isset($data['featured']) ? (bool) $data['featured'] : false;

        $event = $this->repository->create($data);

        // Save initial revision
        $this->revisionService->createRevision($event, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'create_event', $event, "Created event: {$event->title}");

        return $event;
    }

    public function update(Event $event, array $data, ?int $userId): Event
    {
        if (isset($data['featured'])) {
            $data['featured'] = (bool) $data['featured'];
        }

        $this->repository->update($event, $data);

        // Save revision
        $this->revisionService->createRevision($event, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'update_event', $event, "Updated event: {$event->title}");

        return $event;
    }

    public function delete(Event $event, ?int $userId): bool
    {
        $title = $event->title;
        $deleted = $this->repository->delete($event);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_event', $event, "Deleted event: {$title}");
        }

        return $deleted;
    }

    public function rollback(Event $event, int $version, ?int $userId): bool
    {
        return $this->revisionService->rollback($event, $version, $userId);
    }

    public function getRevisions(Event $event)
    {
        return $this->revisionService->getRevisions($event);
    }

    public function getRegistrations(Event $event, int $perPage = 25)
    {
        return $event->registrations()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
