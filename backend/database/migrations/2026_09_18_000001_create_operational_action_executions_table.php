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
        if (! Schema::hasTable('operational_action_executions')) {
            Schema::create('operational_action_executions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('operational_alert_id')->constrained('operational_alerts')->cascadeOnDelete();
                $table->string('action_key', 64)->index();
                $table->string('status', 32)->default('requested')->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->json('before_snapshot')->nullable();
                $table->json('after_snapshot')->nullable();
                $table->text('result_summary')->nullable();
                $table->string('error_code', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['action_key', 'status', 'created_at'], 'idx_op_action_exec_key_stat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_action_executions');
    }
};
