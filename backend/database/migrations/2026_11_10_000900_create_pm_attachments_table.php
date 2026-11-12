<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pm_attachments table for project-level and task-level private file attachments.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_attachments')) {
            Schema::create('pm_attachments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->foreignId('task_id')->nullable()->constrained('pm_tasks')->onDelete('set null');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('original_name');
                $table->string('storage_name');
                $table->string('file_path');
                $table->string('disk')->default('local');
                $table->string('mime_type', 128);
                $table->unsignedBigInteger('file_size');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id'], 'idx_pm_attachments_project');
                $table->index(['task_id'], 'idx_pm_attachments_task');
                $table->index(['user_id'], 'idx_pm_attachments_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_attachments');
    }
};

