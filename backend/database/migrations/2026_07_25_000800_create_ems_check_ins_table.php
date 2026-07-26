<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance records.
 *
 * Foundation only — Phase 1 provides no check-in endpoint and no QR scanner.
 * The unique index on ticket_id is what will make a ticket single-use in
 * Phase 4 without any application-level race condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_check_ins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('ems_tickets')
                ->nullOnDelete();

            $table->foreignId('registration_id')
                ->nullable()
                ->constrained('ems_registrations')
                ->nullOnDelete();

            // The staff member who performed the check-in.
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('checked_in_at');
            $table->string('method', 32)->default('manual');
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            // A ticket can only be redeemed once. Walk-ins carry a null
            // ticket_id, and every database we target allows repeated nulls
            // in a unique index.
            $table->unique('ticket_id', 'ems_check_ins_ticket_unique');
            $table->index(['event_id', 'checked_in_at'], 'ems_check_ins_event_time_idx');
            $table->index('registration_id', 'ems_check_ins_registration_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_check_ins');
    }
};
