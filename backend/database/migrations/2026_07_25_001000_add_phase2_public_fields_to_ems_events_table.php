<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 extensions for public discovery.
 *
 * Adds an optional banner URL so public listing and landing pages can show a
 * hero image without restructuring the Phase 1 event aggregate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->string('banner_url', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropColumn('banner_url');
        });
    }
};
