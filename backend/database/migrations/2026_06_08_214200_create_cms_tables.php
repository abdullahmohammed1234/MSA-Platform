<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Homepage Sections
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->integer('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('status')->default('published'); // draft, published, archived
            $table->timestamps();
        });

        // 2. Homepage Content Blocks
        Schema::create('homepage_content_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained('homepage_sections')->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, textarea, image, url, list
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // 3. Announcements
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('summary')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Events
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('location');
            $table->string('date'); // Custom display date e.g. "Every Friday" or "2026-06-15"
            $table->string('time'); // Custom display time e.g. "1:30 PM" or "6:00 PM - 8:30 PM"
            $table->dateTime('start_date'); // Used for ordering / filtering
            $table->dateTime('end_date')->nullable();
            $table->string('registration_url')->nullable();
            $table->string('image')->nullable();
            $table->string('category'); // Jummah, Social, Lecture, Workshop, Charity, Dinner
            $table->string('status')->default('draft'); // draft, published, archived
            $table->integer('spots_left')->default(0);
            $table->boolean('featured')->default(false);
            $table->date('registration_deadline')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Team Members
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('role');
            $table->string('dept'); // President, Vice Presidents, Directors, Secretary, Media Team, Events Team, Coordinators
            $table->string('img')->nullable();
            $table->text('bio')->nullable();
            $table->string('email')->nullable();
            $table->string('linkedin')->nullable();
            $table->integer('display_order')->default(0);
            $table->string('status')->default('published'); // draft, published, archived
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Resources
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('category'); // New Muslim, Student Guides, Prayer, Mental Health, Learning, Campus Survival, Chaplaincy, Community
            $table->string('icon_name'); // Sparkles, Users, GraduationCap, etc.
            $table->string('link');
            $table->boolean('is_external')->default(false);
            $table->json('tags')->nullable();
            $table->string('file')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('status')->default('published'); // draft, published, archived
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Media Library
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('filename');
            $table->string('filepath');
            $table->string('url');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 8. CMS Revisions / Version History
        Schema::create('cms_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisable_type');
            $table->unsignedBigInteger('revisable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('content');
            $table->integer('version');
            $table->timestamps();

            $table->index(['revisable_type', 'revisable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revisions');
        Schema::dropIfExists('media');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('events');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('homepage_content_blocks');
        Schema::dropIfExists('homepage_sections');
    }
};
