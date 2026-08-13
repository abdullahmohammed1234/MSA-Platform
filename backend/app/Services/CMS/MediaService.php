<?php

namespace App\Services\CMS;

use App\Models\CMS\Media;
use App\Repositories\CMS\MediaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    protected $repository;
    protected $revisionService;

    public function __construct(MediaRepository $repository, RevisionService $revisionService)
    {
        $this->repository = $repository;
        $this->revisionService = $revisionService;
    }

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->repository->list($filters, $perPage);
    }

    public function findByUuid(string $uuid): ?Media
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * @param  array{display_name?: string|null, category_id?: int|null}  $meta
     */
    public function upload(UploadedFile $file, ?int $userId, array $meta = []): Media
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';

        $originalBase = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalBase);
        if ($safeName === '') {
            $safeName = 'media';
        }

        // Never use user-provided display names as filesystem paths.
        $storedFilename = $safeName.'-'.time().'-'.Str::lower(Str::random(6)).'.'.$extension;

        $filepath = $file->storeAs('uploads', $storedFilename, 'public');
        $url = Storage::disk('public')->url($filepath);

        $displayName = isset($meta['display_name']) ? trim((string) $meta['display_name']) : '';
        $displayName = $displayName !== '' ? $displayName : null;

        $categoryId = $meta['category_id'] ?? null;

        $media = $this->repository->create([
            'uuid' => (string) Str::uuid(),
            'filename' => $file->getClientOriginalName(),
            'display_name' => $displayName,
            'category_id' => $categoryId,
            'filepath' => $filepath,
            'url' => $url,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        $this->revisionService->logAction(
            $userId,
            'upload_media',
            $media,
            'Uploaded media asset: '.$media->resolvedDisplayName()
        );

        Cache::forget('website_media');

        return $media;
    }

    public function delete(Media $media, ?int $userId): bool
    {
        if (Storage::disk('public')->exists($media->filepath)) {
            Storage::disk('public')->delete($media->filepath);
        }

        $label = $media->resolvedDisplayName();
        $deleted = $this->repository->delete($media);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_media', $media, "Deleted media asset: {$label}");
            Cache::forget('website_media');
        }

        return $deleted;
    }
}
