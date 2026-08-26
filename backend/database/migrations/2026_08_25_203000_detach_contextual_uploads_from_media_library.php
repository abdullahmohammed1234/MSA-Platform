<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove media-library rows for images that were uploaded via contextual forms
 * (event banners, announcements, courses, homepage, etc.). Keeps the files on
 * disk so existing URLs continue to work.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('media')) {
            return;
        }

        $referenced = collect();

        if (Schema::hasTable('ems_events') && Schema::hasColumn('ems_events', 'banner_url')) {
            $referenced = $referenced->merge(
                DB::table('ems_events')->whereNotNull('banner_url')->pluck('banner_url')
            );
        }

        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'featured_image')) {
            $referenced = $referenced->merge(
                DB::table('announcements')->whereNotNull('featured_image')->pluck('featured_image')
            );
        }

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'image')) {
            $referenced = $referenced->merge(
                DB::table('events')->whereNotNull('image')->pluck('image')
            );
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'thumbnail')) {
            $referenced = $referenced->merge(
                DB::table('courses')->whereNotNull('thumbnail')->pluck('thumbnail')
            );
        }

        if (Schema::hasTable('resources') && Schema::hasColumn('resources', 'thumbnail')) {
            $referenced = $referenced->merge(
                DB::table('resources')->whereNotNull('thumbnail')->pluck('thumbnail')
            );
        }

        if (Schema::hasTable('homepage_content_blocks')) {
            $referenced = $referenced->merge(
                DB::table('homepage_content_blocks')
                    ->where('type', 'image')
                    ->whereNotNull('value')
                    ->pluck('value')
            );
        }

        $filepaths = $referenced
            ->map(fn ($value) => $this->toStorageFilepath((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($filepaths->isEmpty()) {
            return;
        }

        // Delete library rows only — do not remove files from disk.
        DB::table('media')->whereIn('filepath', $filepaths->all())->delete();

        Cache::forget('website_media');
    }

    public function down(): void
    {
        // Irreversible: media-library metadata for contextual uploads is not restored.
    }

    private function toStorageFilepath(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $trimmed;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (!str_starts_with($normalized, 'uploads/')) {
            return null;
        }

        return $normalized;
    }
};
