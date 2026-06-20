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
        Schema::create('job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_uuid')->unique()->nullable();
            $table->string('job_name');
            $table->string('queue')->index();
            $table->string('status')->index(); // pending, processing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->float('duration', 8, 3)->nullable(); // duration in seconds (millisecond resolution)
            $table->text('failure_reason')->nullable();
            $table->integer('attempts')->default(1);
            $table->longText('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_logs');
    }
};
