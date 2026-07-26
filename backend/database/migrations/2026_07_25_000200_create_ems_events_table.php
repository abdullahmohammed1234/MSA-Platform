<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EMS event aggregate root.
 *
 * Deliberately separate from the CMS `events` table, which backs the public
 * marketing website. The EMS owns the operational event record: lifecycle,
 * capacity, organizer assignment and, from Phase 2, registrations and tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();

            // Deleting a category with events attached is rejected by the
            // service with a 409 before the database ever sees it; the
            // restrict keeps the invariant true even for direct SQL.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('ems_event_categories')
                ->restrictOnDelete();

            // The accountable owner. Co-organizers live on ems_event_organizers.
            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('location', 255)->nullable();

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('timezone', 64)->default('America/Vancouver');

            $table->unsignedInteger('capacity')->nullable();

            $table->string('status', 32)->default('draft');

            // Lifecycle timestamps, written only by the lifecycle service.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('registration_open_at')->nullable();
            $table->timestamp('registration_closed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            // Phase 2 seam: whether the event may surface on public channels.
            $table->boolean('is_public')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Dashboard and list queries filter by status and order by date.
            $table->index(['status', 'start_at'], 'ems_events_status_start_idx');
            $table->index('start_at', 'ems_events_start_idx');
            $table->index(['is_public', 'status'], 'ems_events_public_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_events');
    }
};
