<?php

namespace App\Repositories\CMS;

use App\Models\CMS\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MediaRepository
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Media::with([
            'uploader:id,name',
            'category:id,uuid,name,slug',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('filename', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['media_type'])) {
            $type = $filters['media_type'];
            if ($type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($type === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            } elseif ($type === 'document') {
                $query->where('mime_type', 'not like', 'image/%')
                    ->where('mime_type', 'not like', 'video/%');
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Media
    {
        return Media::with(['uploader:id,name', 'category:id,uuid,name,slug'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $data): Media
    {
        $media = Media::create($data);

        return $media->load(['uploader:id,name', 'category:id,uuid,name,slug']);
    }

    public function delete(Media $media): bool
    {
        return $media->delete();
    }
}
