<?php

namespace App\Services\Academy;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Academy/DAMS-owned contextual image storage.
 *
 * Files live on the shared public disk under uploads/academy/.
 * This never creates CMS media-library rows and must not be invoked
 * through CMS permission middleware.
 */
class AcademyAssetService
{
    public function storeImage(UploadedFile $file): string
    {
        [, $url] = $this->storeUploadedFile($file, 'uploads/academy');

        return $url;
    }

    /**
     * @return array{0: string, 1: string, 2: string} filepath, url, extension
     */
    protected function storeUploadedFile(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';

        $originalBase = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalBase);
        if ($safeName === '') {
            $safeName = 'academy-asset';
        }

        $storedFilename = $safeName.'-'.time().'-'.Str::lower(Str::random(6)).'.'.$extension;

        $filepath = $file->storeAs($directory, $storedFilename, 'public');
        $url = Storage::disk('public')->url($filepath);

        return [$filepath, $url, $extension];
    }
}
