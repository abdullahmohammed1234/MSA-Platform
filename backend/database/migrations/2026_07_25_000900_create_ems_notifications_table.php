<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound event communications.
 *
 * Foundation only — Phase 5 owns confirmation emails, reminders and campaigns.
 * This table is the delivery ledger those jobs will write to; it is separate
 * from the platform's `notifications` table, which serves in-app academy and
 * CMS notifications and has a different shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->nullable()->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('registration_id')
                ->nullable()
                ->constrained('ems_registrations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            // Kept alongside user_id so a guest registration can still be
            // notified without an account.
            $table->string('recipient_email', 255)->nullable();

            $table->string('channel', 32)->default('mail');

            // e.g. registration_confirmed, event_reminder, event_cancelled.
            $table->string('type', 64);

            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();

            $table->string('status', 32)->default('pending');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error', 500)->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status'], 'ems_notifications_event_status_idx');
            $table->index(['status', 'scheduled_at'], 'ems_notifications_dispatch_idx');
            $table->index('user_id', 'ems_notifications_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_notifications');
    }
};
