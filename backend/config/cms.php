<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Media Library
    |--------------------------------------------------------------------------
    |
    | Upload limits are in kilobytes (Laravel's `max` rule unit).
    | Ensure PHP upload_max_filesize / post_max_size can accommodate video_max_kb.
    |
    */
    'media' => [
        'max_image_kb' => (int) env('CMS_MEDIA_MAX_IMAGE_KB', 10240),
        'max_video_kb' => (int) env('CMS_MEDIA_MAX_VIDEO_KB', 51200),
        'max_document_kb' => (int) env('CMS_MEDIA_MAX_DOCUMENT_KB', 10240),
        'image_mimes' => ['jpeg', 'png', 'jpg', 'gif', 'svg', 'webp'],
        'video_mimes' => ['mp4', 'webm', 'mov', 'ogv'],
        'document_mimes' => ['pdf', 'doc', 'docx', 'zip'],
    ],
];
