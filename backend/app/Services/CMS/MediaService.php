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

    public function upload(UploadedFile $file, ?int $userId): Media
    {
        // 1. Generate unique file name to prevent collision
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = $safeName . '-' . time() . '.' . $extension;

        // 2. Save file to public disk under uploads directory
        // Under the hood, this puts it in storage/app/public/uploads
        $filepath = $file->storeAs('uploads', $filename, 'public');

        // 3. Generate accessible asset URL
        $url = Storage::disk('public')->url($filepath);

        // 4. Register in database
        $media = $this->repository->create([
            'uuid' => (string) Str::uuid(),
            'filename' => $file->getClientOriginalName(),
            'filepath' => $filepath,
            'url' => $url,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        // Audit Log
        $this->revisionService->logAction($userId, 'upload_media', $media, "Uploaded media asset: {$media->filename}");

        Cache::forget('website_media');

        return $media;
    }

    public function delete(Media $media, ?int $userId): bool
    {
        // Delete from storage disk
        if (Storage::disk('public')->exists($media->filepath)) {
            Storage::disk('public')->delete($media->filepath);
        }

        $filename = $media->filename;
        $deleted = $this->repository->delete($media);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_media', $media, "Deleted media asset: {$filename}");
            Cache::forget('website_media');
        }

        return $deleted;
    }
}
