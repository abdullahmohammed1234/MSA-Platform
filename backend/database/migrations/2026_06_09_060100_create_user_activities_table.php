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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('activity_type')->index(); // e.g. course_completed, certificate_generated, role_assigned, content_published
            $table->text('description');
            $table->string('subject_type')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
