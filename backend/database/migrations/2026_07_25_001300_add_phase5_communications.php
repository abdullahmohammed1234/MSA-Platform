<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Communications & Notification Automation.
 *
 * Extends the Phase 1 ems_notifications ledger and adds templates, per-event
 * reminder schedules, preference rows, and cancellation metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('archived_at');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
        });

        Schema::create('ems_email_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->string('category', 64);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ems_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->unsignedInteger('offset_value');
            $table->string('offset_unit', 16); // minutes|hours|days
            $table->boolean('enabled')->default(true);
            $table->string('template_key', 64)->default('event_reminder');
            $table->string('audience', 32)->default('confirmed'); // all|confirmed|ticket_holders
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'next_run_at'], 'ems_reminders_due_idx');
            $table->index('event_id', 'ems_reminders_event_idx');
        });

        Schema::create('ems_reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained('ems_event_reminders')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('ems_registrations')->cascadeOnDelete();
            $table->foreignId('notification_id')
                ->nullable()
                ->constrained('ems_notifications')
                ->nullOnDelete();
            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->unique(['reminder_id', 'registration_id'], 'ems_reminder_dispatch_unique');
        });

        Schema::create('ems_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email', 255)->nullable();
            $table->boolean('event_reminders')->default(true);
            $table->boolean('event_updates')->default(true);
            $table->boolean('feedback_requests')->default(true);
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('post_event')->default(true);
            $table->timestamps();

            $table->unique('user_id', 'ems_notif_prefs_user_unique');
            $table->unique('email', 'ems_notif_prefs_email_unique');
        });

        Schema::table('ems_notifications', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('registration_id')
                ->constrained('ems_orders')
                ->nullOnDelete();
            $table->foreignId('payment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('ems_payments')
                ->nullOnDelete();
            $table->foreignId('ticket_id')
                ->nullable()
                ->after('payment_id')
                ->constrained('ems_tickets')
                ->nullOnDelete();
            $table->string('template_key', 64)->nullable()->after('type');
            $table->string('idempotency_key', 120)->nullable()->after('template_key');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('error');
            $table->timestamp('queued_at')->nullable()->after('scheduled_at');
            $table->timestamp('last_attempt_at')->nullable()->after('queued_at');
            $table->string('queue_status', 32)->nullable()->after('status');

            $table->unique('idempotency_key', 'ems_notifications_idempotency_unique');
            $table->index(['type', 'status'], 'ems_notifications_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ems_notifications', function (Blueprint $table) {
            $table->dropUnique('ems_notifications_idempotency_unique');
            $table->dropIndex('ems_notifications_type_status_idx');
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('ticket_id');
            $table->dropColumn([
                'template_key',
                'idempotency_key',
                'retry_count',
                'queued_at',
                'last_attempt_at',
                'queue_status',
            ]);
        });

        Schema::dropIfExists('ems_notification_preferences');
        Schema::dropIfExists('ems_reminder_dispatches');
        Schema::dropIfExists('ems_event_reminders');
        Schema::dropIfExists('ems_email_templates');

        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
