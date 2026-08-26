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
    | New CMS files are stored under uploads/cms/ on the public disk.
    | Academy course assets use uploads/academy/ via AcademyAssetService.
    |
    */
    'media' => [
        'max_image_kb' => (int) env('CMS_MEDIA_MAX_IMAGE_KB', 10240),
        'max_video_kb' => (int) env('CMS_MEDIA_MAX_VIDEO_KB', 51200),
        'max_document_kb' => (int) env('CMS_MEDIA_MAX_DOCUMENT_KB', 10240),
        'image_mimes' => ['jpeg', 'png', 'jpg', 'gif', 'svg', 'webp'],
        'video_mimes' => ['mp4', 'webm', 'mov', 'ogv'],
        'document_mimes' => ['pdf', 'doc', 'docx', 'zip'],
        'disk_directory' => 'uploads/cms',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application boundary metadata (Systems registry / docs)
    |--------------------------------------------------------------------------
    */
    'application' => [
        'name' => 'Content Management System',
        'slug' => 'cms',
        'frontend_path' => '/cms',
        'owns' => [
            'homepage_sections',
            'homepage_content_blocks',
            'announcements',
            'team_members',
            'resources',
            'media',
            'media_categories',
            'cms_revisions',
        ],
        'does_not_own' => [
            'users',
            'roles',
            'permissions',
            'courses',
            'ems_events',
            'legacy_cms_events',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy CMS events (Phase 9 — retired from application use)
    |--------------------------------------------------------------------------
    |
    | Tables `events` and `event_registrations` are retained for archival /
    | historical inspection. Application routes return 410 Gone.
    | EMS is the sole event authority.
    |
    */
    'legacy_events' => [
        'status' => 'archived',
        'api' => 'retired_410',
        'tables' => ['events', 'event_registrations'],
        'replacement' => '/api/v1/ems/public/events',
        'drop_schema' => false,
    ],
];
