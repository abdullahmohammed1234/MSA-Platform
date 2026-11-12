<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the participating departments pivot table for multi-department PMS projects.
     */
    public function up(): void
    {
        Schema::create('pm_project_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['project_id', 'department_id'], 'uq_pm_proj_dept');
            $table->index(['project_id'], 'idx_pm_proj_dept_proj');
            $table->index(['department_id'], 'idx_pm_proj_dept_dept');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_project_departments');
    }
};
