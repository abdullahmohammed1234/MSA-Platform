<?php

namespace App\Services\CMS;

use App\Models\CMS\TeamMember;
use App\Repositories\CMS\TeamRepository;
use Illuminate\Support\Str;

class TeamService
{
    protected $repository;
    protected $revisionService;

    public function __construct(TeamRepository $repository, RevisionService $revisionService)
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

    public function findByUuid(string $uuid): ?TeamMember
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data, ?int $userId): TeamMember
    {
        $data['uuid'] = (string) Str::uuid();

        // Calculate auto display order if not provided
        if (!isset($data['display_order'])) {
            $data['display_order'] = TeamMember::max('display_order') + 1;
        }

        $member = $this->repository->create($data);

        // Save revision
        $this->revisionService->createRevision($member, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'create_team_member', $member, "Created team member: {$member->name}");

        return $member;
    }

    public function update(TeamMember $member, array $data, ?int $userId): TeamMember
    {
        $this->repository->update($member, $data);
        $member->refresh();

        // Save revision
        $this->revisionService->createRevision($member, $userId);

        // Audit Log
        $this->revisionService->logAction($userId, 'update_team_member', $member, "Updated team member: {$member->name}");

        return $member;
    }

    public function delete(TeamMember $member, ?int $userId): bool
    {
        $name = $member->name;
        $deleted = $this->repository->delete($member);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_team_member', $member, "Deleted team member: {$name}");
        }

        return $deleted;
    }

    public function reorder(array $uuids, ?int $userId): bool
    {
        foreach ($uuids as $order => $uuid) {
            $member = $this->repository->findByUuid($uuid);
            if ($member) {
                $member->update(['display_order' => $order]);
            }
        }

        $this->revisionService->logAction($userId, 'reorder_team', null, "Reordered team member listing.");
        return true;
    }

    public function rollback(TeamMember $member, int $version, ?int $userId): bool
    {
        return $this->revisionService->rollback($member, $version, $userId);
    }

    public function getRevisions(TeamMember $member)
    {
        return $this->revisionService->getRevisions($member);
    }
}
