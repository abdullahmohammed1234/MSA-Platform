<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('student_id');
            $table->string('status')->default('confirmed');
            $table->timestamps();

            $table->unique(['event_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
