<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable EMS ↔ Square mapping, refunds, checkout resume, webhook states,
 * and nullable guest emails for in-person walk-ins/POS sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_square_catalog_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->unique()->constrained('ems_ticket_types')->cascadeOnDelete();

            $table->string('square_catalog_item_id', 191)->nullable();
            $table->string('square_catalog_variation_id', 191)->nullable();
            $table->string('square_location_id', 191)->nullable();

            $table->unsignedBigInteger('catalog_item_version')->nullable();
            $table->unsignedBigInteger('catalog_variation_version')->nullable();

            $table->string('sync_status', 32)->default('pending');
            $table->boolean('ems_managed')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_conflict_at')->nullable();
            $table->string('last_conflict_summary', 500)->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);

            $table->timestamps();

            $table->index('square_catalog_variation_id', 'ems_sq_map_variation_idx');
            $table->index('square_catalog_item_id', 'ems_sq_map_item_idx');
            $table->index(['event_id', 'sync_status'], 'ems_sq_map_event_status_idx');
        });

        Schema::create('ems_square_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('payment_id')->constrained('ems_payments')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('ems_orders')->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('ems_registrations')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('provider_refund_id', 191)->nullable()->unique();
            $table->string('idempotency_key', 64)->unique();

            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('CAD');
            $table->string('status', 32)->default('pending');
            $table->string('reason', 192)->nullable();
            $table->string('failure_reason', 500)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status'], 'ems_sq_refunds_payment_status_idx');
        });

        Schema::create('ems_square_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('cursor_value', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('ems_payments', function (Blueprint $table) {
            $table->text('checkout_url')->nullable()->after('provider_transaction_id');
            $table->timestamp('checkout_expires_at')->nullable()->after('checkout_url');
            $table->string('source_channel', 32)->default('online')->after('checkout_expires_at');
            $table->string('terminal_checkout_id', 191)->nullable()->after('source_channel');
            $table->string('terminal_device_id', 191)->nullable()->after('terminal_checkout_id');

            $table->index('source_channel', 'ems_payments_source_idx');
            $table->index('terminal_checkout_id', 'ems_payments_terminal_idx');
        });

        Schema::table('ems_orders', function (Blueprint $table) {
            $table->string('source_channel', 32)->default('online')->after('status');
        });

        Schema::table('ems_webhook_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('retry_count');
        });

    }

    public function down(): void
    {
        Schema::table('ems_orders', function (Blueprint $table) {
            $table->dropColumn('source_channel');
        });

        Schema::table('ems_webhook_events', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_attempt_at']);
        });

        Schema::table('ems_payments', function (Blueprint $table) {
            $table->dropIndex('ems_payments_source_idx');
            $table->dropIndex('ems_payments_terminal_idx');
            $table->dropColumn([
                'checkout_url',
                'checkout_expires_at',
                'source_channel',
                'terminal_checkout_id',
                'terminal_device_id',
            ]);
        });

        Schema::dropIfExists('ems_square_sync_cursors');
        Schema::dropIfExists('ems_square_refunds');
        Schema::dropIfExists('ems_square_catalog_mappings');
    }
};
