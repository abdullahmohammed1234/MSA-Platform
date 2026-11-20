<?php

namespace App\Pm\Support;

class PmFrontendUrl
{
    /**
     * Absolute Projects SPA URL for a project workspace.
     */
    public static function project(string $uuid): string
    {
        return rtrim((string) config('app.projects_url'), '/') . '/projects/' . ltrim($uuid, '/');
    }

    /**
     * Relative SPA path used by in-app notification action_url.
     */
    public static function projectPath(string $uuid): string
    {
        return '/projects/' . ltrim($uuid, '/');
    }
}
