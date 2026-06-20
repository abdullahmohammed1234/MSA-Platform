<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type')->index(); // e.g. failed_login, permission_violation, rate_limit_violation, suspicious_request
            $table->string('severity')->default('medium')->index(); // low, medium, high, critical
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('payload')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
