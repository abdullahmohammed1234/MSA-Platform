<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Orders, waitlists, webhook idempotency and paid-event capacity rules.
 *
 * Additive only: free registration from Phase 2 continues to work unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('reference', 32)->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('buyer_name', 180);
            $table->string('buyer_email', 255);
            $table->string('buyer_phone', 32)->nullable();

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('CAD');

            $table->string('status', 32)->default('pending');

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status'], 'ems_orders_event_status_idx');
            $table->index('buyer_email', 'ems_orders_buyer_email_idx');
        });

        Schema::create('ems_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('order_id')->constrained('ems_orders')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ems_ticket_types')
                ->nullOnDelete();

            $table->string('name', 120);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->char('currency', 3)->default('CAD');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('order_id', 'ems_order_items_order_idx');
        });

        Schema::create('ems_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ems_ticket_types')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registration_id')
                ->nullable()
                ->constrained('ems_registrations')
                ->nullOnDelete();

            $table->string('attendee_name', 180);
            $table->string('attendee_email', 255);
            $table->string('attendee_phone', 32)->nullable();

            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('quantity')->default(1);

            $table->string('status', 32)->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'attendee_email', 'status'], 'ems_waitlist_event_email_status_uq');
            $table->index(['event_id', 'status', 'position'], 'ems_waitlist_queue_idx');
        });

        Schema::create('ems_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('event_type', 120)->nullable();
            $table->string('status', 32)->default('processed');

            $table->foreignId('order_id')->nullable()->constrained('ems_orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('ems_payments')->nullOnDelete();

            $table->json('payload')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'ems_webhook_provider_event_uq');
            $table->index(['provider', 'event_type'], 'ems_webhook_provider_type_idx');
        });

        Schema::table('ems_events', function (Blueprint $table) {
            $table->boolean('waitlist_enabled')->default(false)->after('capacity');
            $table->unsignedSmallInteger('max_tickets_per_order')->nullable()->after('waitlist_enabled');
            $table->unsignedSmallInteger('max_registrations_per_attendee')->nullable()->after('max_tickets_per_order');
            $table->timestamp('registration_deadline_at')->nullable()->after('max_registrations_per_attendee');
        });

        Schema::table('ems_ticket_types', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('is_active');
            $table->unsignedSmallInteger('max_per_order')->nullable()->after('is_visible');
        });

        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('ticket_type_id')
                ->constrained('ems_orders')
                ->nullOnDelete();

            $table->unsignedInteger('waitlist_position')->nullable()->after('quantity');

            $table->index('order_id', 'ems_registrations_order_idx');
        });

        Schema::table('ems_payments', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('registration_id')
                ->constrained('ems_orders')
                ->nullOnDelete();

            $table->string('provider_checkout_id', 191)->nullable()->after('provider_order_id');
            $table->string('provider_transaction_id', 191)->nullable()->after('provider_checkout_id');

            $table->index('order_id', 'ems_payments_order_idx');
            $table->index('provider_checkout_id', 'ems_payments_checkout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ems_payments', function (Blueprint $table) {
            $table->dropIndex('ems_payments_order_idx');
            $table->dropIndex('ems_payments_checkout_idx');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['provider_checkout_id', 'provider_transaction_id']);
        });

        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->dropIndex('ems_registrations_order_idx');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('waitlist_position');
        });

        Schema::table('ems_ticket_types', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'max_per_order']);
        });

        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropColumn([
                'waitlist_enabled',
                'max_tickets_per_order',
                'max_registrations_per_attendee',
                'registration_deadline_at',
            ]);
        });

        Schema::dropIfExists('ems_webhook_events');
        Schema::dropIfExists('ems_waitlist_entries');
        Schema::dropIfExists('ems_order_items');
        Schema::dropIfExists('ems_orders');
    }
};
