<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('donation_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('donor_name');
            $table->string('donor_email');
            
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency')->default('CAD');
            
            $table->string('status')->default('pending'); // pending, paid, failed, cancelled, refunded
            $table->boolean('is_anonymous')->default(false);
            $table->text('dedication')->nullable();
            
            $table->string('square_checkout_id')->nullable()->index();
            $table->string('square_order_id')->nullable()->index();
            $table->string('square_payment_id')->nullable()->index();
            
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['donor_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
