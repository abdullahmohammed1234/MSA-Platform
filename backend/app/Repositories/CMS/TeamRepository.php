<?php

namespace App\Repositories\CMS;

use App\Models\CMS\TeamMember;
use Illuminate\Database\Eloquent\Builder;

class TeamRepository
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = TeamMember::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['dept'])) {
            $query->where('dept', $filters['dept']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('display_order', 'asc')->paginate($perPage);
    }

    public function getPublished()
    {
        return TeamMember::where('status', 'published')
            ->orderBy('display_order', 'asc')
            ->get();
    }

    public function findByUuid(string $uuid): ?TeamMember
    {
        return TeamMember::where('uuid', $uuid)->first();
    }

    public function create(array $data): TeamMember
    {
        return TeamMember::create($data);
    }

    public function update(TeamMember $member, array $data): bool
    {
        return $member->update($data);
    }

    public function delete(TeamMember $member): bool
    {
        return $member->delete();
    }
}
