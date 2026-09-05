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
        Schema::create('platform_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('alert_key', 255);
            $table->string('application', 64);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->string('title', 255);
            $table->text('message');
            $table->json('context')->nullable();
            $table->enum('status', ['new', 'acknowledged', 'resolved'])->default('new');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity'], 'idx_alerts_status_severity');
            $table->index(['application', 'created_at'], 'idx_alerts_app_created');
            $table->index('alert_key', 'idx_alerts_key');
        });

        Schema::create('platform_health_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('system_status', ['operational', 'degraded', 'unavailable', 'unknown']);
            $table->unsignedInteger('operational_count')->default(0);
            $table->unsignedInteger('degraded_count')->default(0);
            $table->unsignedInteger('unavailable_count')->default(0);
            $table->json('details');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index('recorded_at', 'idx_health_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_health_histories');
        Schema::dropIfExists('platform_alerts');
    }
};
