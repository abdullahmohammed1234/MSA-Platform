<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->down();

        Schema::create('mlibms_authors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('biography')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mlibms_publishers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mlibms_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('mlibms_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mlibms_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('shelf_identifier')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('mlibms_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->foreignId('primary_category_id')->nullable()->constrained('mlibms_categories')->nullOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('mlibms_publishers')->nullOnDelete();
            $table->string('isbn_10', 10)->nullable()->index();
            $table->string('isbn_13', 13)->nullable()->index();
            $table->string('edition', 50)->nullable();
            $table->unsignedInteger('publication_year')->nullable();
            $table->string('language', 50)->default('English');
            $table->text('summary')->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->unsignedInteger('default_loan_days')->nullable();
            $table->boolean('is_reference_only')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['title', 'isbn_13']);
        });

        Schema::create('mlibms_book_authors', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained('mlibms_books')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('mlibms_authors')->cascadeOnDelete();
            $table->string('role', 50)->default('author');
            $table->primary(['book_id', 'author_id', 'role']);
        });

        Schema::create('mlibms_copies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('book_id')->constrained('mlibms_books')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('mlibms_locations')->nullOnDelete();
            $table->string('barcode', 100)->unique();
            $table->string('accession_number', 100)->unique();
            $table->string('condition', 50)->default('good'); // new, good, fair, worn, damaged
            $table->string('status', 50)->default('available'); // available, checked_out, reserved, lost, damaged, maintenance, retired
            $table->date('acquisition_date')->nullable();
            $table->unsignedInteger('acquisition_cost_cents')->nullable();
            $table->unsignedInteger('replacement_cost_cents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['book_id', 'status']);
        });

        Schema::create('mlibms_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('library_card_number', 50)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('membership_type', 50)->default('student'); // student, faculty_staff, community_guest
            $table->string('status', 50)->default('active'); // active, suspended, expired
            $table->unsignedInteger('max_active_loans')->default(3);
            $table->text('notes')->nullable();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'status']);
        });

        Schema::create('mlibms_loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('copy_id')->constrained('mlibms_copies')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('mlibms_members')->restrictOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedInteger('renewed_count')->default(0);
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('status', 50)->default('active'); // active, returned, overdue, lost, damaged
            $table->text('staff_notes')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['copy_id', 'status']);
            $table->index(['due_at', 'status']);
        });

        Schema::create('mlibms_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('mlibms_loans')->cascadeOnDelete();
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('previous_due_at')->nullable();
            $table->timestamp('new_due_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('mlibms_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('book_id')->constrained('mlibms_books')->cascadeOnDelete();
            $table->foreignId('copy_id')->nullable()->constrained('mlibms_copies')->nullOnDelete();
            $table->foreignId('member_id')->constrained('mlibms_members')->cascadeOnDelete();
            $table->string('status', 50)->default('pending'); // pending, ready_for_pickup, fulfilled, cancelled, expired
            $table->unsignedInteger('queue_position')->default(1);
            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'status', 'queue_position']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('mlibms_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mlibms_settings');
        Schema::dropIfExists('mlibms_reservations');
        Schema::dropIfExists('mlibms_renewals');
        Schema::dropIfExists('mlibms_loans');
        Schema::dropIfExists('mlibms_members');
        Schema::dropIfExists('mlibms_copies');
        Schema::dropIfExists('mlibms_book_authors');
        Schema::dropIfExists('mlibms_books');
        Schema::dropIfExists('mlibms_locations');
        Schema::dropIfExists('mlibms_categories');
        Schema::dropIfExists('mlibms_publishers');
        Schema::dropIfExists('mlibms_authors');
    }
};
