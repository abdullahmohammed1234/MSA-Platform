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
        // 1. Drop old certificates table
        Schema::dropIfExists('certificates');

        // 2. Certificate Templates
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('title_template');
            $table->text('description_template')->nullable();
            $table->string('layout')->default('landscape'); // landscape, portrait
            $table->json('branding')->nullable(); // primary_color, secondary_color, logo_url
            $table->json('signatures')->nullable(); // signatures info: name, title, signature_image_path
            $table->string('background_asset')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Certificates (Definitions)
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_template_id')->constrained('certificate_templates')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // course, learning_path, manual
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('cascade');
            $table->foreignId('learning_path_id')->nullable()->constrained('learning_paths')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'course_id']);
            $table->index(['type', 'learning_path_id']);
        });

        // 4. Certificate Requirements
        Schema::create('certificate_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->string('type'); // lesson_completion, quiz_completion, passing_score, course_completion, average_score, custom
            $table->json('parameters')->nullable(); // e.g. {"percentage": 100}, {"passing_score": 80}
            $table->timestamps();
            $table->softDeletes();

            $table->index('certificate_id');
        });

        // 5. Certificate Awards (User's earned certificates)
        Schema::create('certificate_awards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->string('title'); // snapshot at time of award
            $table->text('description')->nullable();
            $table->string('code')->unique(); // unique code e.g. CERT-XXXX-YYYY
            $table->string('verification_token')->unique(); // token for verify URL
            $table->string('pdf_path')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null'); // admin issuer if manual
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'certificate_id']);
            $table->index('code');
            $table->index('verification_token');
        });

        // 6. Certificate Verifications (Lookup logging)
        Schema::create('certificate_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_award_id')->constrained('certificate_awards')->onDelete('cascade');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('verified_at')->useCurrent();
            $table->timestamps();

            $table->index('certificate_award_id');
        });

        // 7. Achievements
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // completion, performance, participation, special_recognition
            $table->unsignedInteger('points')->default(0);
            $table->string('criteria_type'); // course_completed, quiz_passed, score_reached, lessons_count, courses_count, path_completed, manual
            $table->json('criteria_value')->nullable(); // e.g. {"course_id": 1}, {"lessons_completed": 10}
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Achievement Awards (User unlocked achievements)
        Schema::create('achievement_awards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained('achievements')->onDelete('cascade');
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
            $table->index('user_id');
        });

        // 9. Badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('criteria_type');
            $table->json('criteria_value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 10. Badge Awards (User earned badges)
        Schema::create('badge_awards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('badge_id')->constrained('badges')->onDelete('cascade');
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
            $table->index('user_id');
        });

        // 11. Milestones
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // courses_completed, lessons_completed, paths_completed
            $table->unsignedInteger('threshold');
            $table->timestamps();
            $table->softDeletes();
        });

        // 12. Milestone Awards (User earned milestones)
        Schema::create('milestone_awards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('milestone_id')->constrained('milestones')->onDelete('cascade');
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'milestone_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_awards');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('badge_awards');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('achievement_awards');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('certificate_verifications');
        Schema::dropIfExists('certificate_awards');
        Schema::dropIfExists('certificate_requirements');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');

        // Recreate the basic old certificates table
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('template')->nullable();
            $table->string('code')->unique();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }
};
