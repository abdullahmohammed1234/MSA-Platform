<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Durable "recipients were notified" marker so an announcement fans out once,
 * on first publication, and never again on edit or unpublish/republish.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements') || Schema::hasColumn('announcements', 'notified_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('published_at');
        });

        // Announcements that are or were published already fanned out under the old code.
        DB::table('announcements')
            ->whereNull('notified_at')
            ->where(function ($query) {
                $query->where('status', 'published')->orWhereNotNull('published_at');
            })
            ->update(['notified_at' => DB::raw('COALESCE(published_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'notified_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('notified_at');
            });
        }
    }
};
