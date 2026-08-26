<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Image-only upload used by contextual forms (events, homepage, etc.).
 * Stores the file and returns a URL — does not create a media-library row.
 */
class UploadAssetRequest extends FormRequest
{
    private const MIME_ALIASES = [
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml', 'image/svg'],
        'webp' => ['image/webp'],
    ];

    private const BLOCKED_MIMES = [
        'text/x-php',
        'application/x-php',
        'application/x-httpd-php',
        'text/html',
        'application/x-msdownload',
        'application/x-executable',
        'application/x-dosexec',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('file');
            if (!$file instanceof UploadedFile) {
                return;
            }

            if (!$file->isValid()) {
                $validator->errors()->add('file', 'The file failed to upload. Please try a smaller file or a different format.');

                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $allowed = array_map('strtolower', config('cms.media.image_mimes', ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp']));

            if ($extension === '' || !in_array($extension, $allowed, true)) {
                $validator->errors()->add('file', 'Please choose an image file (JPEG, PNG, WEBP, GIF, or SVG).');

                return;
            }

            $detectedMime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));

            if (in_array($detectedMime, self::BLOCKED_MIMES, true)) {
                $validator->errors()->add('file', 'Please choose an image file (JPEG, PNG, WEBP, GIF, or SVG).');

                return;
            }

            if (!$this->mimeIsAllowedForExtension($extension, $detectedMime)) {
                $validator->errors()->add('file', 'Please choose an image file (JPEG, PNG, WEBP, GIF, or SVG).');

                return;
            }

            $max = (int) config('cms.media.max_image_kb', 10240);
            $sizeKb = (int) ceil($file->getSize() / 1024);
            if ($sizeKb > $max) {
                $validator->errors()->add('file', "The image may not be greater than {$max} kilobytes.");
            }
        });
    }

    private function mimeIsAllowedForExtension(string $extension, string $detectedMime): bool
    {
        if ($detectedMime === '' || $detectedMime === 'application/octet-stream') {
            return true;
        }

        $aliases = self::MIME_ALIASES[$extension] ?? [];
        if (in_array($detectedMime, $aliases, true)) {
            return true;
        }

        return str_starts_with($detectedMime, 'image/');
    }
}
