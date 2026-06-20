<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('discussion_posts')->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['user_id', 'thread_id', 'type'], 'discussion_thread_reaction_unique');
            $table->unique(['user_id', 'post_id', 'type'], 'discussion_post_reaction_unique');
        });

        Schema::create('discussion_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'thread_id']);
        });

        Schema::create('discussion_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('discussion_posts')->cascadeOnDelete();
            $table->string('reason');
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_reports');
        Schema::dropIfExists('discussion_bookmarks');
        Schema::dropIfExists('discussion_reactions');
    }
};
