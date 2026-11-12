<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the pivot table for multiple task assignees in PMS.
     */
    public function up(): void
    {
        Schema::create('pm_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pm_tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['task_id', 'user_id'], 'uq_pm_task_user');
            $table->index(['user_id'], 'idx_pm_task_assignee_user');
            $table->index(['task_id'], 'idx_pm_task_assignee_task');
        });

        // Seed existing single-assignee records into pm_task_assignees to preserve existing assignments
        if (Schema::hasTable('pm_tasks')) {
            $existingTasks = DB::table('pm_tasks')->whereNotNull('assigned_to')->get();
            foreach ($existingTasks as $task) {
                DB::table('pm_task_assignees')->insertOrIgnore([
                    'task_id' => $task->id,
                    'user_id' => $task->assigned_to,
                    'assigned_by' => $task->created_by,
                    'assigned_at' => $task->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_task_assignees');
    }
};
