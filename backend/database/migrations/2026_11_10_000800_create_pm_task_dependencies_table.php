<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates relational dependencies between tasks in the same project.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_task_dependencies')) {
            Schema::create('pm_task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->foreignId('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('depends_on_task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->unique(['task_id', 'depends_on_task_id'], 'uq_pm_task_dependencies');
                $table->index(['depends_on_task_id'], 'idx_pm_task_dep_prereq');
                $table->index(['project_id'], 'idx_pm_task_dep_project');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_task_dependencies');
    }
};

