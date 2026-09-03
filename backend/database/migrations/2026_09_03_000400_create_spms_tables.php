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
        // 1. Organizations
        Schema::create('spms_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('legal_name', 255);
            $table->string('display_name', 255);
            $table->string('relationship_type', 50)->default('sponsor');
            $table->string('status', 50)->default('prospect');
            $table->string('industry', 100)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 50)->default('BC');
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 50)->default('Canada');
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_publicly_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Contacts
        Schema::create('spms_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('spms_organizations')->onDelete('cascade');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('title', 100)->nullable();
            $table->string('email', 255)->index();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('preferred_contact_method', 50)->default('email');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Opportunities
        Schema::create('spms_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('opportunity_type', 50)->default('event');
            $table->foreignId('event_id')->nullable()->constrained('ems_events')->onDelete('set null');
            $table->unsignedBigInteger('target_amount_cents')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('status', 50)->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 4. Packages
        Schema::create('spms_packages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('opportunity_id')->nullable()->constrained('spms_opportunities')->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->integer('max_available')->nullable();
            $table->integer('claimed_count')->default(0);
            $table->boolean('is_customizable')->default(false);
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 5. Package Benefits
        Schema::create('spms_package_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('spms_packages')->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('deliverable_type', 50)->default('other');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        // 6. Sponsorships
        Schema::create('spms_sponsorships', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('sponsorship_number', 50)->unique();
            $table->foreignId('organization_id')->constrained('spms_organizations');
            $table->foreignId('contact_id')->nullable()->constrained('spms_contacts')->onDelete('set null');
            $table->foreignId('opportunity_id')->nullable()->constrained('spms_opportunities')->onDelete('set null');
            $table->foreignId('package_id')->nullable()->constrained('spms_packages')->onDelete('set null');
            $table->string('title', 255);
            $table->string('sponsorship_type', 50)->default('package');
            $table->string('status', 50)->default('prospect');
            $table->string('financial_status', 50)->default('uncommitted');
            $table->string('fulfillment_status', 50)->default('not_started');
            $table->unsignedBigInteger('total_committed_cents')->default(0);
            $table->unsignedBigInteger('total_paid_cents')->default(0);
            $table->unsignedBigInteger('in_kind_estimated_cents')->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('relationship_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Agreements
        Schema::create('spms_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->string('agreement_number', 50)->unique();
            $table->string('status', 50)->default('draft');
            $table->timestamp('signed_at')->nullable();
            $table->longText('terms_and_conditions')->nullable();
            $table->string('document_url', 500)->nullable();
            $table->string('executed_by_name', 255)->nullable();
            $table->string('executed_by_title', 255)->nullable();
            $table->timestamps();
        });

        // 8. Commitments
        Schema::create('spms_commitments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->string('commitment_type', 50)->default('cash');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->date('due_date');
            $table->string('status', 50)->default('pending');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        // 9. Payments
        Schema::create('spms_payments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->foreignId('commitment_id')->nullable()->constrained('spms_commitments')->onDelete('set null');
            $table->string('payment_method', 50)->default('square');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('CAD');
            $table->string('status', 50)->default('pending');
            $table->string('reference_number', 100)->nullable();
            $table->string('square_checkout_id', 255)->nullable()->index();
            $table->string('square_order_id', 255)->nullable()->index();
            $table->string('square_payment_id', 255)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 10. In-Kind Contributions
        Schema::create('spms_in_kind_contributions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->string('category', 50)->default('other');
            $table->text('description');
            $table->unsignedBigInteger('estimated_value_cents')->default(0);
            $table->unsignedBigInteger('agreed_value_cents')->default(0);
            $table->integer('quantity')->default(1);
            $table->timestamp('received_at')->nullable();
            $table->string('status', 50)->default('promised');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Deliverables
        Schema::create('spms_deliverables', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('deliverable_type', 50)->default('other');
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status', 50)->default('not_started');
            $table->timestamps();
        });

        // 12. Fulfillments
        Schema::create('spms_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('spms_deliverables')->onDelete('cascade');
            $table->timestamp('completed_at');
            $table->foreignId('completed_by')->constrained('users');
            $table->string('proof_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 13. Communications
        Schema::create('spms_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('spms_organizations')->onDelete('cascade');
            $table->foreignId('sponsorship_id')->nullable()->constrained('spms_sponsorships')->onDelete('set null');
            $table->foreignId('contact_id')->nullable()->constrained('spms_contacts')->onDelete('set null');
            $table->foreignId('logged_by')->constrained('users');
            $table->string('interaction_type', 50)->default('email');
            $table->string('subject', 255);
            $table->text('body');
            $table->timestamp('interaction_at');
            $table->timestamps();
        });

        // 14. Follow-Ups
        Schema::create('spms_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('spms_organizations')->onDelete('cascade');
            $table->foreignId('sponsorship_id')->nullable()->constrained('spms_sponsorships')->onDelete('set null');
            $table->foreignId('assigned_to')->constrained('users');
            $table->string('title', 255);
            $table->date('due_date');
            $table->string('status', 50)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 15. Renewals
        Schema::create('spms_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('previous_sponsorship_id')->constrained('spms_sponsorships')->onDelete('cascade');
            $table->foreignId('new_sponsorship_id')->nullable()->constrained('spms_sponsorships')->onDelete('set null');
            $table->date('target_renewal_date');
            $table->unsignedBigInteger('proposed_amount_cents')->default(0);
            $table->string('status', 50)->default('pending_outreach');
            $table->foreignId('owner_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spms_renewals');
        Schema::dropIfExists('spms_follow_ups');
        Schema::dropIfExists('spms_communications');
        Schema::dropIfExists('spms_fulfillments');
        Schema::dropIfExists('spms_deliverables');
        Schema::dropIfExists('spms_in_kind_contributions');
        Schema::dropIfExists('spms_payments');
        Schema::dropIfExists('spms_commitments');
        Schema::dropIfExists('spms_agreements');
        Schema::dropIfExists('spms_sponsorships');
        Schema::dropIfExists('spms_package_benefits');
        Schema::dropIfExists('spms_packages');
        Schema::dropIfExists('spms_opportunities');
        Schema::dropIfExists('spms_contacts');
        Schema::dropIfExists('spms_organizations');
    }
};
