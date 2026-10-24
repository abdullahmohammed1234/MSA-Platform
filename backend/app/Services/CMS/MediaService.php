<?php

namespace App\Services\CMS;

use App\Models\CMS\Media;
use App\Repositories\CMS\MediaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * @param  array{display_name?: string|null, category_id?: int|null, audience?: string|null}  $meta
     */
    public function upload(UploadedFile $file, ?int $userId, array $meta = []): Media
    {
        $rawAudience = $meta['audience'] ?? 'both';
        $audience = match ($rawAudience) {
            'brothers', 'male' => 'brothers',
            'sisters', 'female' => 'sisters',
            default => 'both',
        };

        $isRestricted = in_array($audience, ['brothers', 'sisters'], true);
        $disk = $isRestricted ? 'local' : 'public';
        $directory = $isRestricted ? 'private/uploads/cms' : 'uploads/cms';

        $uuid = (string) Str::uuid();

        [$filepath, $url, $extension] = $this->storeUploadedFile($file, $directory, $disk);

        if ($isRestricted) {
            $url = "/api/v1/website/media/{$uuid}/file";
        }

        $displayName = isset($meta['display_name']) ? trim((string) $meta['display_name']) : '';
        $displayName = $displayName !== '' ? $displayName : null;

        $categoryId = $meta['category_id'] ?? null;

        $media = $this->repository->create([
            'uuid' => $uuid,
            'filename' => $file->getClientOriginalName(),
            'display_name' => $displayName,
            'category_id' => $categoryId,
            'filepath' => $filepath,
            'url' => $url,
            'mime_type' => $this->resolveMimeType($file, $extension),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
            'audience' => $audience,
        ]);

        $this->revisionService->logAction(
            $userId,
            'upload_media',
            $media,
            'Uploaded media asset: '.$media->resolvedDisplayName()
        );

        $this->forgetMediaCaches();

        return $media;
    }

    /**
     * Update existing media item metadata and handle storage relocation
     * if the audience changes between public (both) and restricted (brothers/sisters).
     */
    public function update(Media $media, ?int $userId, array $data): Media
    {
        $oldAudience = $media->audience ?? 'both';
        $rawAudience = $data['audience'] ?? $oldAudience;
        $newAudience = match ($rawAudience) {
            'brothers', 'male' => 'brothers',
            'sisters', 'female' => 'sisters',
            default => 'both',
        };

        $oldIsRestricted = in_array($oldAudience, ['brothers', 'sisters', 'male', 'female'], true);
        $newIsRestricted = in_array($newAudience, ['brothers', 'sisters'], true);

        $oldPath = $media->filepath;
        $newPath = $oldPath;
        $newUrl = $media->url;

        $needsRelocation = false;
        $sourceDisk = null;
        $targetDisk = null;
        $sourcePath = $oldPath;
        $targetPath = $oldPath;

        if ($newIsRestricted && $oldPath && Storage::disk('public')->exists($oldPath)) {
            // Relocate from public storage to local protected storage
            $needsRelocation = true;
            $sourceDisk = 'public';
            $targetDisk = 'local';
            $filename = basename($oldPath);
            $targetPath = 'private/uploads/cms/' . $filename;
            $newPath = $targetPath;
            $newUrl = "/api/v1/website/media/{$media->uuid}/file";
        } elseif (!$newIsRestricted && $oldPath && Storage::disk('local')->exists($oldPath)) {
            // Relocate from local protected storage to public storage
            $needsRelocation = true;
            $sourceDisk = 'local';
            $targetDisk = 'public';
            $filename = basename($oldPath);
            $targetPath = 'uploads/cms/' . $filename;
            $newPath = $targetPath;
            $newUrl = Storage::disk('public')->url($targetPath);
        }

        $updateData = [
            'audience' => $newAudience,
            'filepath' => $newPath,
            'url' => $newUrl,
        ];

        if (array_key_exists('display_name', $data)) {
            $displayName = trim((string) $data['display_name']);
            $updateData['display_name'] = $displayName !== '' ? $displayName : null;
        }

        if (array_key_exists('category_id', $data)) {
            $updateData['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        }

        if ($needsRelocation && $sourceDisk && $targetDisk && $sourcePath && $targetPath) {
            // Step 1: Copy to target disk before touching DB or source
            $contents = Storage::disk($sourceDisk)->get($sourcePath);
            $written = Storage::disk($targetDisk)->put($targetPath, $contents);

            if (!$written || !Storage::disk($targetDisk)->exists($targetPath)) {
                throw new \RuntimeException("Failed to relocate media asset to {$targetDisk} disk: {$targetPath}");
            }

            // Step 2: Update DB within transaction with compensation if DB fails
            try {
                $media = DB::transaction(function () use ($media, $updateData) {
                    return $this->repository->update($media, $updateData);
                });
            } catch (\Throwable $e) {
                // Compensate: remove target copy so no orphan file remains
                Storage::disk($targetDisk)->delete($targetPath);
                throw $e;
            }

            // Step 3: Delete old source file only AFTER DB transaction has committed
            Storage::disk($sourceDisk)->delete($sourcePath);
        } else {
            $media = DB::transaction(function () use ($media, $updateData) {
                return $this->repository->update($media, $updateData);
            });
        }

        $this->revisionService->logAction(
            $userId,
            'update_media',
            $media,
            'Updated media asset: ' . $media->resolvedDisplayName() . " (Audience: {$newAudience})"
        );

        $this->forgetMediaCaches();

        return $media;
    }

    /**
     * Store a CMS-owned contextual image (homepage, announcements, etc.)
     * without creating a media-library row.
     *
     * New uploads use uploads/cms/. Legacy files under uploads/ remain valid.
     * Academy/course assets must use AcademyAssetService — not this method.
     */
    public function storeAsset(UploadedFile $file): string
    {
        [, $url] = $this->storeUploadedFile($file, 'uploads/cms', 'public');

        return $url;
    }

    /**
     * @return array{0: string, 1: ?string, 2: string} filepath, url, extension
     */
    protected function storeUploadedFile(UploadedFile $file, string $directory, string $disk = 'public'): array
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

        $filepath = $file->storeAs($directory, $storedFilename, $disk);
        $url = $disk === 'public' ? Storage::disk('public')->url($filepath) : null;

        return [$filepath, $url, $extension];
    }

    public function delete(Media $media, ?int $userId): bool
    {
        if ($media->filepath && Storage::disk('public')->exists($media->filepath)) {
            Storage::disk('public')->delete($media->filepath);
        }
        if ($media->filepath && Storage::disk('local')->exists($media->filepath)) {
            Storage::disk('local')->delete($media->filepath);
        }

        $label = $media->resolvedDisplayName();
        $deleted = $this->repository->delete($media);

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_media', $media, "Deleted media asset: {$label}");
            $this->forgetMediaCaches();
        }

        return $deleted;
    }

    public function forgetMediaCaches(): void
    {
        Cache::forget('website_media');
        Cache::forget('website_media_public');
        Cache::forget('website_media_male');
        Cache::forget('website_media_female');
    }

    /**
     * Prefer a concrete image/video MIME. Some hosts report application/octet-stream
     * from finfo, which breaks CMS preview rendering.
     */
    protected function resolveMimeType(UploadedFile $file, string $extension): string
    {
        $detected = strtolower((string) ($file->getMimeType() ?: ''));
        $client = strtolower((string) ($file->getClientMimeType() ?: ''));

        foreach ([$detected, $client] as $candidate) {
            if (str_starts_with($candidate, 'image/') || str_starts_with($candidate, 'video/')) {
                return $candidate;
            }
        }

        $extensionMap = [
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'ogv' => 'video/ogg',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
        ];

        if (isset($extensionMap[$extension])) {
            return $extensionMap[$extension];
        }

        if ($detected !== '' && $detected !== 'application/octet-stream') {
            return $detected;
        }

        if ($client !== '') {
            return $client;
        }

        return 'application/octet-stream';
    }
}
