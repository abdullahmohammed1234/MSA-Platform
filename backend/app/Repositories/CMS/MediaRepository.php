<?php

namespace App\Repositories\CMS;

use App\Models\CMS\Media;
use Illuminate\Database\Eloquent\Builder;

class MediaRepository
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Media::with('uploader:id,name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('filename', 'like', "%{$search}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Media
    {
        return Media::where('uuid', $uuid)->first();
    }

    public function create(array $data): Media
    {
        return Media::create($data);
    }

    public function delete(Media $media): bool
    {
        return $media->delete();
    }
}
