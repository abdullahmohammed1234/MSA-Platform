<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scenario_id');
            $table->string('scenario_title');
            $table->string('category')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('character_name')->nullable();
            $table->string('avatar_seed')->nullable();
            $table->unsignedInteger('overall_score')->default(0);
            $table->unsignedTinyInteger('atmosphere_score')->default(0);
            $table->json('transcript')->nullable();
            $table->json('reflections')->nullable();
            $table->boolean('is_bookmarked')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
            $table->index(['user_id', 'scenario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_sessions');
    }
};
