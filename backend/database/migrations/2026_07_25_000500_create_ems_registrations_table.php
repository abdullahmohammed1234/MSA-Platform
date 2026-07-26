<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendee registrations.
 *
 * Foundation only. Phase 1 exposes no registration endpoints. The shape
 * supports both free and paid registration and both authenticated members
 * (user_id) and guests (attendee_email) so Phase 2 can open public sign-up
 * without altering the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Human-quotable reference shown on confirmations, e.g. REG-8F3K2A.
            $table->string('reference', 32)->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();

            // Null for guest registrations made without an MSA account.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ems_ticket_types')
                ->nullOnDelete();

            $table->string('attendee_name', 180);
            $table->string('attendee_email', 255);
            $table->string('attendee_phone', 32)->nullable();

            $table->string('status', 32)->default('pending');
            $table->string('type', 16)->default('free');

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->char('currency', 3)->default('CAD');

            $table->timestamp('registered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('notes', 500)->nullable();

            // Free-form answers to custom registration questions (Phase 8).
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status'], 'ems_registrations_event_status_idx');
            $table->index(['event_id', 'attendee_email'], 'ems_registrations_event_email_idx');
            $table->index('user_id', 'ems_registrations_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_registrations');
    }
};
