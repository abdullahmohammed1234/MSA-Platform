<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_digest_runs', function (Blueprint $table) {
            $table->id();
            // Registrations assigned to this run carry the same value in digest_batch.
            $table->uuid('batch')->unique();
            // One logical digest per period; the period ends at cutoff_at (exclusive).
            $table->date('period_date')->unique();
            // dateTime, not timestamp: MariaDB < 10.10 (explicit_defaults_for_timestamp=OFF)
            // gives the first NOT NULL TIMESTAMP column ON UPDATE CURRENT_TIMESTAMP, which
            // would silently move the cutoff on every claim.
            $table->dateTime('cutoff_at');
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('registrations_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_digest_runs');
    }
};
