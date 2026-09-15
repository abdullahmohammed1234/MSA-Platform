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
        if (! Schema::hasTable('operational_action_approvals')) {
            Schema::create('operational_action_approvals', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('operational_alert_id')->constrained('operational_alerts')->cascadeOnDelete();
                $table->string('action_key', 64)->index();
                $table->string('status', 32)->default('pending')->index();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('request_reason')->nullable();
                $table->text('decision_reason')->nullable();
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['action_key', 'status', 'created_at'], 'idx_op_action_approval_key_stat');
                $table->index(['operational_alert_id', 'action_key', 'status'], 'idx_op_action_approval_alert_key');
            });
        }

        if (Schema::hasTable('operational_action_executions') && ! Schema::hasColumn('operational_action_executions', 'approval_id')) {
            Schema::table('operational_action_executions', function (Blueprint $table) {
                $table->foreignId('approval_id')->nullable()->after('operational_alert_id')->constrained('operational_action_approvals')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_action_approvals');
    }
};
