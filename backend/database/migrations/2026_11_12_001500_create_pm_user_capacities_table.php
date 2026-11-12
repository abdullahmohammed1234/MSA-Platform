<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pm_user_capacities table for member working availability and capacity tracking.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pm_user_capacities')) {
            Schema::create('pm_user_capacities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->decimal('weekly_capacity_hours', 5, 2)->default(10.00);
                $table->decimal('daily_capacity_hours', 4, 2)->default(2.00);
                $table->json('daily_availability')->nullable();
                $table->unsignedInteger('max_concurrent_tasks')->default(5);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('notes', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['user_id', 'effective_from', 'effective_to'], 'idx_pm_user_cap_dates');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_user_capacities');
    }
};

