<?php

namespace App\Services\CMS;

use App\Events\AnnouncementPublishedEvent;
use App\Models\CMS\Announcement;
use App\Repositories\CMS\AnnouncementRepository;
use Illuminate\Support\Str;

class AnnouncementService
{
    protected $repository;
    protected $revisionService;

    public function __construct(AnnouncementRepository $repository, RevisionService $revisionService)
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

    public function findByUuid(string $uuid): ?Announcement
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data, ?int $userId): Announcement
    {
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = Str::slug($data['title']);
        $data['author_id'] = $userId;

        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        $announcement = $this->repository->create($data);

        // Save initial revision
        $this->revisionService->createRevision($announcement, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'create_announcement', $announcement, "Created announcement: {$announcement->title}");

        if ($announcement->status === 'published') {
            $this->dispatchPublishedEvent($announcement);
        }

        return $announcement;
    }

    public function update(Announcement $announcement, array $data, ?int $userId): Announcement
    {
        $wasPublished = $announcement->status === 'published';

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        if (isset($data['status'])) {
            if ($data['status'] === 'published' && $announcement->status !== 'published') {
                $data['published_at'] = now();
            } elseif ($data['status'] !== 'published') {
                $data['published_at'] = null;
            }
        }

        $this->repository->update($announcement, $data);
        $announcement->refresh();

        // Save revision
        $this->revisionService->createRevision($announcement, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'update_announcement', $announcement, "Updated announcement: {$announcement->title}");

        if (!$wasPublished && $announcement->status === 'published') {
            $this->dispatchPublishedEvent($announcement);
        }

        return $announcement;
    }

    public function delete(Announcement $announcement, ?int $userId): bool
    {
        $title = $announcement->title;
        $deleted = $this->repository->delete($announcement);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_announcement', $announcement, "Deleted announcement: {$title}");
        }

        return $deleted;
    }

    public function rollback(Announcement $announcement, int $version, ?int $userId): bool
    {
        return $this->revisionService->rollback($announcement, $version, $userId);
    }

    public function getRevisions(Announcement $announcement)
    {
        return $this->revisionService->getRevisions($announcement);
    }

    protected function dispatchPublishedEvent(Announcement $announcement): void
    {
        $excerpt = $announcement->summary
            ?: Str::limit(strip_tags($announcement->content), 150);

        event(new AnnouncementPublishedEvent(
            $announcement->title,
            $excerpt,
            $announcement->slug,
            'All'
        ));
    }
}
