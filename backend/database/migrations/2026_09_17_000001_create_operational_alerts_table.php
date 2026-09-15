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
        if (! Schema::hasTable('operational_alerts')) {
            Schema::create('operational_alerts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('fingerprint', 64)->unique();
                $table->string('category', 32)->index();
                $table->string('severity', 16)->index();
                $table->string('status', 16)->default('open')->index();
                $table->string('title');
                $table->text('description');
                $table->string('source_type', 64)->index();
                $table->string('source_id', 64)->index();
                $table->string('rule_key', 64)->index();
                $table->string('action_url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('first_detected_at')->useCurrent();
                $table->timestamp('last_detected_at')->useCurrent();
                $table->timestamp('acknowledged_at')->nullable();
                $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('dismissed_at')->nullable();
                $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity', 'created_at'], 'idx_op_alerts_stat_sev_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
    }
};
