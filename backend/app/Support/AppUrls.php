<?php

namespace App\Support;

class AppUrls
{
    public static function api(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function frontend(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    /**
     * Rewrite legacy localhost storage URLs saved during local development.
     */
    public static function resolveStorageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('#^https?://localhost(?::\d+)?#i', $url)) {
            return preg_replace('#^https?://localhost(?::\d+)?#i', self::api(), $url) ?? $url;
        }

        return $url;
    }
}
