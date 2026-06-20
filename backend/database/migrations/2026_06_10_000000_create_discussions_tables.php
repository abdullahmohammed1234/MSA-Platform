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
        Schema::create('discussion_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('category_id')->constrained('discussion_categories')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->foreignId('thread_id')->constrained('discussion_threads')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('discussion_posts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
        Schema::dropIfExists('discussion_categories');
    }
};
