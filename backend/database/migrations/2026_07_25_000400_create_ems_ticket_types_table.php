<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket types (price tiers) for an event.
 *
 * Foundation only. Phase 1 exposes no ticket-type endpoints; the table exists
 * so registrations and tickets can reference it from the start and Phase 2/3
 * need no destructive migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('description', 500)->nullable();

            // Zero price means a free tier; currency is stored per row so a
            // future multi-currency event does not need a schema change.
            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('CAD');

            // Null quantity means unlimited within the event capacity.
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);

            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'name'], 'ems_ticket_types_event_name_unique');
            $table->index(['event_id', 'is_active'], 'ems_ticket_types_event_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_ticket_types');
    }
};
