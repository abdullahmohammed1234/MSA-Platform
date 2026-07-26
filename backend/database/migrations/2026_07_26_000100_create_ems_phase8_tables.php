<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Event Templates
        Schema::create('ems_event_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('ems_event_categories')->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('waitlist_enabled')->default(false);
            $table->unsignedInteger('max_tickets_per_order')->nullable();
            $table->unsignedInteger('max_registrations_per_attendee')->nullable();
            $table->unsignedInteger('registration_deadline_offset_days')->nullable();
            $table->json('settings')->nullable(); // holds tickets, reminders, custom fields, email templates, staff roles, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // 2. Event Series (Recurrence parent)
        Schema::create('ems_event_series', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('recurrence_pattern', 32); // daily, weekly, monthly, custom
            $table->unsignedInteger('recurrence_interval')->default(1);
            $table->json('recurrence_days')->nullable(); // e.g. ["monday", "friday"]
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Update ems_events with series and funnel metrics
        Schema::table('ems_events', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('ems_event_series')->nullOnDelete();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('registrations_started_count')->default(0);
        });

        // 4. Promo Codes
        Schema::create('ems_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->string('discount_type', 32); // percentage, fixed, free
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_attendee')->default(1);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->decimal('minimum_purchase', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // 5. Promo Code Event restriction (pivot)
        Schema::create('ems_promo_code_event', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->constrained('ems_promo_codes')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->primary(['promo_code_id', 'event_id']);
        });

        // 6. Promo Code Ticket Type restriction (pivot)
        Schema::create('ems_promo_code_ticket_type', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->constrained('ems_promo_codes')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ems_ticket_types')->cascadeOnDelete();
            $table->primary(['promo_code_id', 'ticket_type_id']);
        });

        // 7. Update ems_registrations for promo code tracking
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('ems_promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
        });

        // 8. Update ems_orders for promo code tracking
        Schema::table('ems_orders', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('ems_promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
        });

        // 9. Feedback & Surveys
        Schema::create('ems_event_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('ems_registrations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('organization_rating');
            $table->unsignedTinyInteger('program_rating');
            $table->unsignedTinyInteger('venue_rating');
            $table->text('text_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_event_feedbacks');

        Schema::table('ems_orders', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });

        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });

        Schema::dropIfExists('ems_promo_code_ticket_type');
        Schema::dropIfExists('ems_promo_code_event');
        Schema::dropIfExists('ems_promo_codes');

        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropColumn(['series_id', 'views_count', 'registrations_started_count']);
        });

        Schema::dropIfExists('ems_event_series');
        Schema::dropIfExists('ems_event_templates');
    }
};
