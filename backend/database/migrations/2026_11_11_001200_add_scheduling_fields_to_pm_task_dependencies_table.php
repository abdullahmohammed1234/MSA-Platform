<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds dependency_type (FS, SS, FF) and lag_days to pm_task_dependencies.
     */
    public function up(): void
    {
        if (Schema::hasTable('pm_task_dependencies')) {
            Schema::table('pm_task_dependencies', function (Blueprint $table) {
                if (!Schema::hasColumn('pm_task_dependencies', 'dependency_type')) {
                    $table->string('dependency_type', 30)->default('finish_to_start')->after('depends_on_task_id');
                }
                if (!Schema::hasColumn('pm_task_dependencies', 'lag_days')) {
                    $table->integer('lag_days')->default(0)->after('dependency_type');
                }
                $table->index(['project_id', 'dependency_type'], 'idx_pm_task_dep_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pm_task_dependencies')) {
            Schema::table('pm_task_dependencies', function (Blueprint $table) {
                if (Schema::hasColumn('pm_task_dependencies', 'dependency_type')) {
                    $table->dropIndex('idx_pm_task_dep_type');
                    $table->dropColumn('dependency_type');
                }
                if (Schema::hasColumn('pm_task_dependencies', 'lag_days')) {
                    $table->dropColumn('lag_days');
                }
            });
        }
    }
};

