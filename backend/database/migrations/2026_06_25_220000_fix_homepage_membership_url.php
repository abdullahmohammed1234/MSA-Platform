<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update hero primary URL if it is pointing to /membership
        DB::table('homepage_content_blocks')
            ->where('key', 'cta_primary_url')
            ->where('value', '/membership')
            ->update(['value' => '/contact']);

        // Update bottom CTA button URL if it is pointing to /membership
        DB::table('homepage_content_blocks')
            ->where('key', 'button_url')
            ->where('value', '/membership')
            ->update(['value' => '/contact']);

        // General fallback check for any other value matching /membership in homepage blocks
        DB::table('homepage_content_blocks')
            ->where('value', '/membership')
            ->update(['value' => '/contact']);

        // Clear the cache to apply changes immediately in production
        Cache::forget('website_homepage');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for data correction
    }
};
