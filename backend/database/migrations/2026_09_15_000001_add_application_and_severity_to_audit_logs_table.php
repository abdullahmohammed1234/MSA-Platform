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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('application', 64)->nullable()->after('user_id');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->after('action');

            $table->index(['application', 'created_at'], 'idx_audit_app_created');
            $table->index(['severity', 'created_at'], 'idx_audit_severity_created');
            $table->index(['action', 'created_at'], 'idx_audit_action_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_app_created');
            $table->dropIndex('idx_audit_severity_created');
            $table->dropIndex('idx_audit_action_created');
            $table->dropColumn(['application', 'severity']);
        });
    }
};
