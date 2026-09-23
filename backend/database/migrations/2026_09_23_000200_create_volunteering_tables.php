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
        Schema::dropIfExists('volunteering_signups');
        Schema::dropIfExists('volunteering_shifts');
        Schema::dropIfExists('volunteering_teams');
        Schema::dropIfExists('volunteering_opportunities');

        Schema::create('volunteering_opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Optional reference to an EMS event (event-linked opportunity)
            $table->foreignId('event_id')->nullable()->constrained('ems_events')->nullOnDelete();

            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();

            $table->string('status', 32)->default('draft')->index(); // draft, open, closed, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status'], 'vol_opp_event_status_idx');
        });

        Schema::create('volunteering_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status', 32)->default('open')->index(); // open, closed
            $table->integer('ordering')->default(0);
            $table->timestamps();

            $table->index(['opportunity_id', 'status'], 'vol_teams_opp_status_idx');
        });

        Schema::create('volunteering_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('volunteering_teams')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->integer('capacity')->default(10);
            $table->string('status', 32)->default('open')->index(); // open, closed
            $table->timestamps();

            $table->index(['opportunity_id', 'team_id'], 'vol_shifts_opp_team_idx');
        });

        Schema::create('volunteering_signups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('opportunity_id')->constrained('volunteering_opportunities')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('volunteering_teams')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('volunteering_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 180);
            $table->string('email', 255)->index();
            $table->string('phone', 32)->nullable();
            $table->text('experience')->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 32)->default('signed_up')->index(); // signed_up, confirmed, cancelled, completed, no_show
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['opportunity_id', 'email', 'shift_id'], 'vol_signups_opp_email_shift_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteering_signups');
        Schema::dropIfExists('volunteering_shifts');
        Schema::dropIfExists('volunteering_teams');
        Schema::dropIfExists('volunteering_opportunities');
    }
};
