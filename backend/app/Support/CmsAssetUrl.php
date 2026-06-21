<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class CmsAssetUrl
{
    /**
     * Resolve a CMS asset path or URL to a browser-loadable URL.
     * Static frontend paths (/Hero, /Team) are returned unchanged.
     */
    public static function resolve(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) {
            return null;
        }

        if (preg_match('#^https?://#i', $pathOrUrl) || str_starts_with($pathOrUrl, 'data:')) {
            return self::fixLocalhostPort($pathOrUrl);
        }

        $normalized = ltrim($pathOrUrl, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return Storage::disk('public')->url($normalized);
        }

        return str_starts_with($pathOrUrl, '/') ? $pathOrUrl : '/' . $pathOrUrl;
    }

    private static function fixLocalhostPort(string $url): string
    {
        return AppUrls::resolveStorageUrl($url) ?? $url;
    }
}
