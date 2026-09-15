<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_event_volunteers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 180);
            $table->string('email', 255);
            $table->string('phone', 32)->nullable();

            $table->json('interests')->nullable();
            $table->string('availability', 255)->nullable();
            $table->text('experience')->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 32)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status'], 'ems_volunteers_event_status_idx');
            $table->index(['event_id', 'email'], 'ems_volunteers_event_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_event_volunteers');
    }
};
