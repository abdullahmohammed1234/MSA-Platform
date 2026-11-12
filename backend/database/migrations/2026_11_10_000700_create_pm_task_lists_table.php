<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pm_task_lists table and adds task_list_id and start_date to pm_tasks.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_task_lists')) {
            Schema::create('pm_task_lists', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->string('name');
                $table->integer('order_index')->default(0);
                $table->string('color', 30)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'order_index'], 'idx_pm_task_lists_project_order');
            });
        }

        if (Schema::hasTable('pm_tasks')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('pm_tasks', 'task_list_id')) {
                    $table->foreignId('task_list_id')->nullable()->after('milestone_id')->constrained('pm_task_lists')->onDelete('set null');
                    $table->index(['project_id', 'task_list_id'], 'idx_pm_tasks_project_list');
                }
                if (!Schema::hasColumn('pm_tasks', 'start_date')) {
                    $table->date('start_date')->nullable()->after('priority');
                    $table->index(['project_id', 'start_date', 'due_date'], 'idx_pm_tasks_project_dates');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pm_tasks')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('pm_tasks', 'task_list_id')) {
                    $table->dropForeign(['task_list_id']);
                    $table->dropIndex('idx_pm_tasks_project_list');
                    $table->dropColumn('task_list_id');
                }
                if (Schema::hasColumn('pm_tasks', 'start_date')) {
                    $table->dropIndex('idx_pm_tasks_project_dates');
                    $table->dropColumn('start_date');
                }
            });
        }

        Schema::dropIfExists('pm_task_lists');
    }
};

