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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('course_completion')->default(true);
            $table->boolean('new_announcements')->default(true);
            $table->boolean('upcoming_training')->default(true);
            $table->boolean('certificate_earned')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
