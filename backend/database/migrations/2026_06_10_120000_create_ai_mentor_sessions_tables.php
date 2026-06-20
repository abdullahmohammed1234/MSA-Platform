<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mentor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mentor_id');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('ai_mentor_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_mentor_sessions')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mentor_messages');
        Schema::dropIfExists('ai_mentor_sessions');
    }
};
