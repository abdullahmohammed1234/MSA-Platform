<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('donation_id')->constrained('donations')->cascadeOnDelete();
            
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency')->default('CAD');
            
            $table->string('reason')->nullable();
            $table->string('square_refund_id')->nullable()->index();
            $table->string('status')->default('completed'); // pending, completed, failed
            
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_refunds');
    }
};
