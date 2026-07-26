<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Event operations, check-in audit trail, and attendee import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_check_ins', function (Blueprint $table) {
            $table->string('device', 64)->nullable()->after('method');
            $table->timestamp('undone_at')->nullable()->after('notes');
            $table->foreignId('undone_by')->nullable()->after('undone_at')->constrained('users')->nullOnDelete();
            $table->string('undo_reason', 255)->nullable()->after('undone_by');
        });

        Schema::create('ems_check_in_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ems_tickets')->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('ems_registrations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('method', 32)->nullable();
            $table->string('result_code', 64)->nullable();
            $table->string('message', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at'], 'ems_check_in_audits_event_time_idx');
            $table->index('action', 'ems_check_in_audits_action_idx');
        });

        Schema::create('ems_attendee_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('source', 32)->default('excel_csv');
            $table->string('status', 32)->default('pending');
            $table->json('column_mapping')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status'], 'ems_attendee_imports_event_status_idx');
        });

        Schema::create('ems_import_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('ems_events')->nullOnDelete();
            $table->json('mapping');
            $table->timestamps();

            $table->index(['user_id', 'name'], 'ems_import_mappings_user_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_import_column_mappings');
        Schema::dropIfExists('ems_attendee_imports');
        Schema::dropIfExists('ems_check_in_audits');

        Schema::table('ems_check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('undone_by');
            $table->dropColumn(['device', 'undone_at', 'undo_reason']);
        });
    }
};
