<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable project-scoped department assignment to tasks.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pm_tasks')) {
            return;
        }

        Schema::table('pm_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_tasks', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('departments')
                    ->nullOnDelete();
                $table->index(['project_id', 'department_id'], 'idx_pm_tasks_proj_dept');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pm_tasks') || ! Schema::hasColumn('pm_tasks', 'department_id')) {
            return;
        }

        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_pm_tasks_proj_dept');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
