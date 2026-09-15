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
        Schema::create('platform_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('release_identifier')->unique();
            $table->string('application_version')->default('1.30.0');
            $table->string('api_version')->default('v1');
            $table->string('frontend_build')->default('2026.09.13-phase30');
            $table->string('environment')->default('production');
            $table->string('status')->default('PLANNED'); // PLANNED, APPROVED, DEPLOYING, DEPLOYED, VERIFYING, SUCCESSFUL, SUCCESSFUL_WITH_WARNINGS, FAILED, CANCELLED, ROLLED_BACK, NOT_VERIFIED
            $table->boolean('is_active')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->text('release_notes')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->string('deployment_status')->default('NOT_VERIFIED');
            $table->string('migration_status')->default('UP_TO_DATE');
            $table->string('post_release_verification_status')->default('NOT_VERIFIED');
            $table->string('rollback_readiness')->default('ROLLBACK_MANUAL'); // ROLLBACK_READY, ROLLBACK_MANUAL, ROLLBACK_NOT_VERIFIED, ROLLBACK_UNAVAILABLE
            $table->text('rollback_notes')->nullable();
            
            $table->foreignId('previous_release_id')->nullable()->constrained('platform_releases')->nullOnDelete();
            $table->json('affected_applications')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'environment']);
            $table->index(['is_active']);
            $table->index(['released_at']);
        });

        Schema::create('platform_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('release_id')->nullable()->constrained('platform_releases')->nullOnDelete();
            $table->string('change_identifier')->unique();
            $table->string('category')->default('backend_code'); // backend_code, frontend_code, migration, configuration, rbac, application_access, scheduled_job, queue, integration, operational_rule, remediation_action, lifecycle_control
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment')->default('production');
            $table->json('affected_applications')->nullable();
            $table->json('affected_services')->nullable();
            $table->string('impact_level')->default('MEDIUM'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->string('validation_status')->default('NOT_VERIFIED'); // VALIDATED, WARNING, FAILED, NOT_VERIFIED
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['release_id', 'category']);
            $table->index(['impact_level']);
            $table->index(['validation_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_changes');
        Schema::dropIfExists('platform_releases');
    }
};
