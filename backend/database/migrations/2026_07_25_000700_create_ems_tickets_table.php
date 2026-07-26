<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issued tickets.
 *
 * Foundation only — Phase 1 issues no tickets and generates no QR codes. The
 * `code` column is the unique identifier a Phase 2 QR payload will encode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The scannable identifier. Unique across the whole system so a
            // scanner never has to disambiguate by event.
            $table->string('code', 64)->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('ems_registrations')->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ems_ticket_types')
                ->nullOnDelete();

            // Populated in Phase 2 when QR generation is implemented.
            $table->string('qr_payload', 255)->nullable();
            $table->timestamp('qr_generated_at')->nullable();

            $table->string('status', 32)->default('issued');

            $table->string('holder_name', 180)->nullable();
            $table->string('holder_email', 255)->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status'], 'ems_tickets_event_status_idx');
            $table->index('registration_id', 'ems_tickets_registration_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_tickets');
    }
};
