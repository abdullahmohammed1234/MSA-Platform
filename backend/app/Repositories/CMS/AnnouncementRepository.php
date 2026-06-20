<?php

namespace App\Repositories\CMS;

use App\Models\CMS\Announcement;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementRepository
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Announcement::with('author:id,name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getPublished()
    {
        return Announcement::where('status', 'published')
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function findByUuid(string $uuid): ?Announcement
    {
        return Announcement::where('uuid', $uuid)->first();
    }

    public function create(array $data): Announcement
    {
        return Announcement::create($data);
    }

    public function update(Announcement $announcement, array $data): bool
    {
        return $announcement->update($data);
    }

    public function delete(Announcement $announcement): bool
    {
        return $announcement->delete();
    }
}
