<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event delivery team: co-organizers and event staff.
 *
 * Both are user<->event assignments, kept in separate tables because they are
 * authorized differently and will diverge in Phase 4 when staff gain check-in
 * shifts and station assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_event_organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('co_organizer');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'ems_event_organizers_unique');
            $table->index('user_id', 'ems_event_organizers_user_idx');
        });

        Schema::create('ems_event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('staff');
            $table->string('notes', 255)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'ems_event_staff_unique');
            $table->index('user_id', 'ems_event_staff_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_event_staff');
        Schema::dropIfExists('ems_event_organizers');
    }
};
