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
        // 1. Projects
        Schema::create('pm_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->enum('visibility', ['department_private', 'shared_msa'])->default('department_private');
            $table->string('status', 50)->default('planning');
            $table->string('priority', 50)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'visibility']);
            $table->index(['status']);
            $table->index(['owner_id']);
            $table->index(['due_date']);
        });

        // 2. Department Memberships
        Schema::create('pm_department_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('department_role', 50)->default('member'); // lead, coordinator, member
            $table->string('status', 50)->default('active'); // active, inactive
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['department_id', 'user_id', 'status'], 'idx_pm_dept_mem_status');
            $table->index(['user_id', 'status'], 'idx_pm_user_dept_status');
        });

        // 3. Project Members
        Schema::create('pm_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('project_role', 50)->default('contributor'); // owner, manager, contributor, viewer
            $table->string('invitation_status', 50)->default('accepted'); // pending, accepted, declined, revoked
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'invitation_status'], 'idx_pm_proj_mem_invitation');
            $table->index(['project_id', 'project_role'], 'idx_pm_proj_mem_role');
        });

        // 4. Milestones
        Schema::create('pm_milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 50)->default('pending'); // pending, reached, delayed
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'due_date']);
            $table->index(['project_id', 'status']);
        });

        // 5. Tasks
        Schema::create('pm_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->foreignId('milestone_id')->nullable()->constrained('pm_milestones')->onDelete('set null');
            $table->foreignId('parent_task_id')->nullable()->constrained('pm_tasks')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 50)->default('todo'); // todo, in_progress, review, completed, cancelled
            $table->string('priority', 50)->default('medium'); // low, medium, high, urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['due_date']);
        });

        // 6. Comments
        Schema::create('pm_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained('pm_tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id']);
            $table->index(['project_id']);
        });

        // 7. Activity Logs
        Schema::create('pm_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });

        // 8. Access Logs (Administrative actions)
        Schema::create('pm_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('cascade');
            $table->string('action', 100);
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_user_id']);
            $table->index(['actor_user_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_access_logs');
        Schema::dropIfExists('pm_activity_logs');
        Schema::dropIfExists('pm_comments');
        Schema::dropIfExists('pm_tasks');
        Schema::dropIfExists('pm_milestones');
        Schema::dropIfExists('pm_project_members');
        Schema::dropIfExists('pm_department_memberships');
        Schema::dropIfExists('pm_projects');
    }
};
