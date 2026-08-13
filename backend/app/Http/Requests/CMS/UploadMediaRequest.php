<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = array_merge(
            config('cms.media.image_mimes', []),
            config('cms.media.video_mimes', []),
            config('cms.media.document_mimes', []),
        );

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $allowed),
            ],
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

            $extension = strtolower($file->getClientOriginalExtension());
            $sizeKb = (int) ceil($file->getSize() / 1024);

            $imageMimes = config('cms.media.image_mimes', []);
            $videoMimes = config('cms.media.video_mimes', []);
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
}
