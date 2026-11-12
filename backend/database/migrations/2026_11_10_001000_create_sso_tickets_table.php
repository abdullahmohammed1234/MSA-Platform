<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates sso_tickets table for short-lived, single-use SSO credential exchange.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sso_tickets')) {
            Schema::create('sso_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_hash', 64)->unique();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('service', 32)->default('pms');
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['ticket_hash', 'service', 'consumed_at'], 'idx_sso_ticket_lookup');
                $table->index(['user_id', 'service'], 'idx_sso_user_service');
                $table->index(['expires_at'], 'idx_sso_expires');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_tickets');
    }
};

