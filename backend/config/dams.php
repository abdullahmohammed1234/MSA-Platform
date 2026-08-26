<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DAMS — Dawah Academy Management System
    |--------------------------------------------------------------------------
    |
    | Application boundary metadata. Identity/RBAC remain on the MSA Platform.
    | HTTP APIs remain at /api/v1/admin/academy/* for backward compatibility.
    | Learner experience remains at /academy and /api/v1/academy/*.
    |
    */
    'application' => [
        'name' => 'Dawah Academy Management System',
        'slug' => 'dams',
        'frontend_path' => '/dams',
        'api_prefix' => 'api/v1/admin/academy',
        'owns_operations' => [
            'courses',
            'modules',
            'lessons',
            'quizzes',
            'questions',
            'learning_paths',
            'achievements',
            'badges',
            'certificates_admin',
            'mentor_assignments',
            'student_administration',
            'progress_administration',
            'discussion_moderation',
            'academy_analytics',
        ],
        'does_not_own' => [
            'users',
            'roles',
            'permissions',
            'cms_resources',
            'cms_media',
            'ems_events',
            'learner_runtime_apis',
        ],
        'access_permissions' => [
            'manage_courses',
            'manage_modules',
            'manage_lessons',
            'manage_quizzes',
            'manage_learning_paths',
            'manage_certificates',
            'manage_certificate_templates',
            'manage_achievements',
            'manage_badges',
            'manage_volunteers',
            'manage_students',
            'manage_mentors',
            'view_progress',
            'manage_progress',
            'manage_discussions',
            'view_analytics',
            'manage_settings',
            'manage_notifications',
        ],
    ],
];
