<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pm_attachment_cleanups table for durable tracking of physical file deletions
     * that fail during force-deletion, enabling reliable retry and reconciliation.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_attachment_cleanups')) {
            Schema::create('pm_attachment_cleanups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->nullable()->constrained('pm_projects')->onDelete('cascade');
                $table->string('file_path');
                $table->string('disk')->default('local');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('status', 32)->default('pending'); // pending, failed, completed
                $table->timestamps();

                $table->index(['status', 'attempts'], 'idx_pm_att_cleanup_status');
                $table->index(['file_path'], 'idx_pm_att_cleanup_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_attachment_cleanups');
    }
};

