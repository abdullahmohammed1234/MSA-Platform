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
        Schema::dropIfExists('volunteering_invitations');
        Schema::dropIfExists('volunteering_opportunity_interests');
        Schema::dropIfExists('volunteering_opportunity_skills');
        Schema::dropIfExists('volunteering_experiences');
        Schema::dropIfExists('volunteering_profile_interests');
        Schema::dropIfExists('volunteering_profile_skills');
        Schema::dropIfExists('volunteering_profiles');
        Schema::dropIfExists('volunteering_interests');
        Schema::dropIfExists('volunteering_skills');

        // 1. Volunteering Skills Taxonomy
        Schema::create('volunteering_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 2. Volunteering Interests Taxonomy
        Schema::create('volunteering_interests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 3. Volunteer Profiles
        Schema::create('volunteering_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->string('experience_level')->default('beginner'); // beginner, intermediate, advanced, expert
            $table->decimal('years_experience', 4, 1)->default(0.0);
            $table->integer('preferred_hours_per_week')->nullable();
            $table->json('availability_days')->nullable(); // ["monday", "tuesday", ...]
            $table->json('availability_times')->nullable(); // ["morning", "afternoon", "evening"]
            $table->json('preferred_categories')->nullable();
            $table->integer('profile_completion_percentage')->default(0);
            $table->string('privacy_level')->default('private'); // private, coordinator_only, public
            $table->timestamps();

            $table->index('experience_level');
        });

        // 4. Volunteer Profile Skills Pivot
        Schema::create('volunteering_profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('volunteering_profiles')->onDelete('cascade');
            $table->foreignId('skill_id')->constrained('volunteering_skills')->onDelete('cascade');
            $table->string('proficiency_level')->default('intermediate'); // beginner, intermediate, advanced, expert
            $table->timestamps();

            $table->unique(['profile_id', 'skill_id'], 'vms_prof_skills_unique');
        });

        // 5. Volunteer Profile Interests Pivot
        Schema::create('volunteering_profile_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('volunteering_profiles')->onDelete('cascade');
            $table->foreignId('interest_id')->constrained('volunteering_interests')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['profile_id', 'interest_id'], 'vms_prof_interests_unique');
        });

        // 6. External Volunteer Experiences
        Schema::create('volunteering_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('volunteering_profiles')->onDelete('cascade');
            $table->string('organization');
            $table->string('role_title');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        // 7. Opportunity Skills Requirements Pivot
        Schema::create('volunteering_opportunity_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->onDelete('cascade');
            $table->foreignId('skill_id')->constrained('volunteering_skills')->onDelete('cascade');
            $table->boolean('is_required')->default(false); // true = Hard requirement, false = Soft preference
            $table->string('min_proficiency')->default('beginner');
            $table->timestamps();

            $table->unique(['opportunity_id', 'skill_id'], 'vms_opp_skills_unique');
        });

        // 8. Opportunity Interests Preference Pivot
        Schema::create('volunteering_opportunity_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->onDelete('cascade');
            $table->foreignId('interest_id')->constrained('volunteering_interests')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['opportunity_id', 'interest_id'], 'vms_opp_interests_unique');
        });

        // 9. Volunteer Invitations
        Schema::create('volunteering_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained('volunteering_teams')->onDelete('set null');
            $table->foreignId('shift_id')->nullable()->constrained('volunteering_shifts')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, accepted, declined, expired
            $table->text('message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['opportunity_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteering_invitations');
        Schema::dropIfExists('volunteering_opportunity_interests');
        Schema::dropIfExists('volunteering_opportunity_skills');
        Schema::dropIfExists('volunteering_experiences');
        Schema::dropIfExists('volunteering_profile_interests');
        Schema::dropIfExists('volunteering_profile_skills');
        Schema::dropIfExists('volunteering_profiles');
        Schema::dropIfExists('volunteering_interests');
        Schema::dropIfExists('volunteering_skills');
    }
};
