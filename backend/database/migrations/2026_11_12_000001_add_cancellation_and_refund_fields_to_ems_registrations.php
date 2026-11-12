<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->string('cancellation_token_hash', 64)->nullable()->index('ems_reg_cancel_token_idx');
            $table->timestamp('cancellation_token_expires_at')->nullable();
            $table->timestamp('cancellation_token_used_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->string('cancellation_initiator_type', 32)->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refund_status', 32)->default('not_applicable')->index('ems_reg_refund_status_idx');
            $table->decimal('refund_amount', 10, 2)->default(0.00);
        });

        Schema::create('ems_cancellation_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('registration_id')->constrained('ems_registrations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ems_ticket_types')->nullOnDelete();
            $table->string('ticket_type_name', 180)->nullable();

            $table->string('attendee_name', 180)->nullable();
            $table->string('attendee_email', 255)->nullable();

            $table->string('initiator_type', 32); // 'guest', 'member', 'admin'
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason', 500)->nullable();
            $table->boolean('refund_requested')->default(false);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_status', 32)->default('not_applicable');

            $table->string('provider_refund_id', 191)->nullable();
            $table->foreignId('square_refund_id')->nullable()->constrained('ems_square_refunds')->nullOnDelete();

            $table->timestamp('cancelled_at');
            $table->timestamp('refunded_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'cancelled_at'], 'ems_canc_audits_event_time_idx');
            $table->index('registration_id', 'ems_canc_audits_reg_idx');
            $table->index('initiator_type', 'ems_canc_audits_initiator_idx');
            $table->index('refund_status', 'ems_canc_audits_refund_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_cancellation_audits');

        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->dropIndex('ems_reg_cancel_token_idx');
            $table->dropIndex('ems_reg_refund_status_idx');
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropColumn([
                'cancellation_token_hash',
                'cancellation_token_expires_at',
                'cancellation_token_used_at',
                'cancellation_reason',
                'cancellation_initiator_type',
                'refund_status',
                'refund_amount',
            ]);
        });
    }
};

