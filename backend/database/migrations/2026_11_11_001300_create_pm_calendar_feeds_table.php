<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pm_calendar_feeds table for secure, revocable RFC 5545 Webcal subscriptions.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_calendar_feeds')) {
            Schema::create('pm_calendar_feeds', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('project_id')->nullable()->constrained('pm_projects')->onDelete('cascade');
                $table->string('token_hash', 64)->unique();
                $table->string('name');
                $table->boolean('include_tasks')->default(true);
                $table->boolean('include_milestones')->default(true);
                $table->boolean('include_project_deadlines')->default(true);
                $table->boolean('only_assigned_to_me')->default(false);
                $table->timestamp('last_accessed_at')->nullable();
                $table->string('last_accessed_ip', 45)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'revoked_at'], 'idx_pm_cal_feeds_user');
                $table->index(['token_hash', 'revoked_at'], 'idx_pm_cal_feeds_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_calendar_feeds');
    }
};

