<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('volunteering_reengagement_logs');
        Schema::dropIfExists('volunteering_user_achievements');
        Schema::dropIfExists('volunteering_achievements');

        // 1. Achievements Definition Taxonomy
        Schema::create('volunteering_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('participation'); // participation, service_hours, consistency, milestones
            $table->string('icon')->default('award');
            $table->string('rule_type'); // completed_opportunities, verified_service_hours, attendance_count, profile_completion
            $table->json('criteria_config')->nullable(); // e.g. {"threshold": 10}
            $table->integer('points')->default(10);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        // 2. User Awarded Achievements Pivot
        Schema::create('volunteering_user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained('volunteering_achievements')->onDelete('cascade');
            $table->timestamp('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->onDelete('set null'); // null = system automatic
            $table->string('trigger_type')->default('automatic'); // automatic, manual
            $table->text('award_reason')->nullable();
            $table->json('metadata')->nullable(); // snapshot of state (e.g. hours count)
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id'], 'vms_user_achievements_unique');
            $table->index(['user_id', 'awarded_at']);
        });

        // 3. Volunteer Re-engagement Outreach Logs
        Schema::create('volunteering_reengagement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('opportunity_id')->nullable()->constrained('volunteering_opportunities')->onDelete('set null');
            $table->string('channel')->default('email');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteering_reengagement_logs');
        Schema::dropIfExists('volunteering_user_achievements');
        Schema::dropIfExists('volunteering_achievements');
    }
};
