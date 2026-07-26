<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments recorded against a registration.
 *
 * Foundation only — no payment provider is integrated in Phase 1. The
 * provider/provider_payment_id pair is the idempotency key a Phase 3 Square
 * webhook will reconcile against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('registration_id')
                ->constrained('ems_registrations')
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->decimal('amount_refunded', 10, 2)->default(0);
            $table->char('currency', 3)->default('CAD');

            $table->string('provider', 32)->default('square');

            // Unique so a replayed provider webhook can never double-record a
            // payment. Nullable while a checkout is still pending.
            $table->string('provider_payment_id', 191)->nullable()->unique();
            $table->string('provider_order_id', 191)->nullable();

            $table->string('status', 32)->default('pending');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('failure_reason', 255)->nullable();

            // Provider response envelope. Never store card data or secrets here.
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['registration_id', 'status'], 'ems_payments_registration_status_idx');
            $table->index('provider_order_id', 'ems_payments_provider_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_payments');
    }
};
