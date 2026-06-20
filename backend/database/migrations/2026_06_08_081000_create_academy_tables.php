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
        // 1. Courses Table
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('difficulty')->default('beginner'); // beginner, intermediate, advanced
            $table->unsignedInteger('estimated_duration')->nullable(); // in minutes
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('uuid');
        });

        // 2. Modules Table
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('estimated_duration')->nullable(); // in minutes
            $table->timestamps();

            $table->index(['course_id', 'order']);
        });

        // 3. Lessons Table
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->text('content')->nullable();
            $table->string('video_url')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('estimated_duration')->nullable(); // in minutes
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['module_id', 'order']);
        });

        // 4. Quizzes Table
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('passing_score')->default(70); // passing percentage
            $table->unsignedInteger('time_limit')->nullable(); // in minutes, null for unlimited
            $table->unsignedInteger('attempt_limit')->nullable(); // null for unlimited
            $table->string('status')->default('draft'); // draft, published
            $table->timestamps();

            $table->index('course_id');
        });

        // 5. Questions Table
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->string('type'); // multiple_choice, multiple_select, true_false, short_answer
            $table->text('question');
            $table->json('options')->nullable(); // for multiple choice/select
            $table->json('correct_answer'); // safe representation of the correct answer
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['quiz_id', 'order']);
        });

        // 6. Enrollments Table (User <-> Course)
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active'); // active, completed, dropped
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index('status');
        });

        // 7. Progress Table (User <-> Lesson)
        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('completion_percentage')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'course_id']);
        });

        // 8. Quiz Attempts Table
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('score')->default(0); // final calculated score/percentage
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
        });

        // 9. Answers Table (User Quiz Responses)
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->json('answer'); // response given by the user
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'question_id']);
        });

        // 10. Certificates Table
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('template')->nullable();
            $table->string('code')->unique(); // unique verification code
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });

        // 11. Learning Paths Table
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 12. Learning Path <-> Course Many-to-Many Table
        Schema::create('learning_path_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['learning_path_id', 'course_id']);
            $table->index(['learning_path_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_path_course');
        Schema::dropIfExists('learning_paths');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('progress');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
    }
};
