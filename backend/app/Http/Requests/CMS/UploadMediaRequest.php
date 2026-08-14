<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadMediaRequest extends FormRequest
{
    /**
     * MIME aliases that PHP/finfo commonly reports for allowed video extensions.
     * iPhone .mov files are often detected as video/x-quicktime, video/x-m4v,
     * video/mp4, or application/octet-stream instead of video/quicktime.
     *
     * @var array<string, list<string>>
     */
    private const MIME_ALIASES = [
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml', 'image/svg'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4', 'application/mp4', 'video/quicktime', 'video/x-m4v'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime', 'video/x-quicktime', 'video/mp4', 'video/x-m4v', 'application/mp4'],
        'ogv' => ['video/ogg', 'application/ogg', 'audio/ogg'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
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
            'display_name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:media_categories,id'],
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
                $validator->errors()->add(
                    'file',
                    $this->uploadErrorMessage($file)
                );

                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $allowedExtensions = $this->allowedExtensions();

            if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                $validator->errors()->add('file', $this->messages()['file.mimes']);

                return;
            }

            $detectedMime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));

            if (in_array($detectedMime, self::BLOCKED_MIMES, true)) {
                $validator->errors()->add('file', $this->messages()['file.mimes']);

                return;
            }

            if (!$this->mimeIsAllowedForExtension($extension, $detectedMime)) {
                $validator->errors()->add('file', $this->messages()['file.mimes']);

                return;
            }

            $sizeKb = (int) ceil($file->getSize() / 1024);
            $videoMimes = config('cms.media.video_mimes', []);
            $imageMimes = config('cms.media.image_mimes', []);
            $documentMimes = config('cms.media.document_mimes', []);

            if (in_array($extension, $videoMimes, true)) {
                $max = (int) config('cms.media.max_video_kb', 51200);
                $label = 'video';
            } elseif (in_array($extension, $imageMimes, true)) {
                $max = (int) config('cms.media.max_image_kb', 10240);
                $label = 'image';
            } elseif (in_array($extension, $documentMimes, true)) {
                $max = (int) config('cms.media.max_document_kb', 10240);
                $label = 'document';
            } else {
                return;
            }

            if ($sizeKb > $max) {
                $validator->errors()->add(
                    'file',
                    "The {$label} may not be greater than {$max} kilobytes."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'Unsupported file type. Allowed: images, videos (MP4/WebM/MOV/OGV), PDF, DOC, DOCX, ZIP.',
            'category_id.exists' => 'The selected media category is invalid.',
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        return array_map('strtolower', array_merge(
            config('cms.media.image_mimes', []),
            config('cms.media.video_mimes', []),
            config('cms.media.document_mimes', []),
        ));
    }

    private function mimeIsAllowedForExtension(string $extension, string $detectedMime): bool
    {
        if ($detectedMime === '' || $detectedMime === 'application/octet-stream') {
            return in_array($extension, config('cms.media.video_mimes', []), true)
                || in_array($extension, config('cms.media.image_mimes', []), true);
        }

        $aliases = self::MIME_ALIASES[$extension] ?? [];
        if (in_array($detectedMime, $aliases, true)) {
            return true;
        }

        $videoExtensions = config('cms.media.video_mimes', []);
        if (in_array($extension, $videoExtensions, true) && str_starts_with($detectedMime, 'video/')) {
            return true;
        }

        $imageExtensions = config('cms.media.image_mimes', []);
        if (in_array($extension, $imageExtensions, true) && str_starts_with($detectedMime, 'image/')) {
            return true;
        }

        return false;
    }

    private function uploadErrorMessage(UploadedFile $file): string
    {
        $maxVideoMb = (int) round(((int) config('cms.media.max_video_kb', 51200)) / 1024);

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "The video may not be greater than {$maxVideoMb} MB. The server also rejected this file because it exceeds PHP upload limits.",
            default => 'The file failed to upload. Please try a smaller file or a different format (MP4 is most compatible).',
        };
    }
}
