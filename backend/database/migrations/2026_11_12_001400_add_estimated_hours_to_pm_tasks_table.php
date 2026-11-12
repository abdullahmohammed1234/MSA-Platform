<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds estimated_hours and effort_source to pm_tasks.
     */
    public function up(): void
    {
        if (Schema::hasTable('pm_tasks')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('pm_tasks', 'estimated_hours')) {
                    $table->decimal('estimated_hours', 6, 2)->nullable()->after('priority');
                }
                if (!Schema::hasColumn('pm_tasks', 'effort_source')) {
                    $table->string('effort_source', 20)->default('inferred')->after('estimated_hours');
                }
                $table->index(['project_id', 'estimated_hours'], 'idx_pm_tasks_proj_effort');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pm_tasks')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('pm_tasks', 'estimated_hours')) {
                    $table->dropIndex('idx_pm_tasks_proj_effort');
                    $table->dropColumn('estimated_hours');
                }
                if (Schema::hasColumn('pm_tasks', 'effort_source')) {
                    $table->dropColumn('effort_source');
                }
            });
        }
    }
};

