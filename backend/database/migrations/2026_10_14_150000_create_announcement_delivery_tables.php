<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // initial:{announcement} | resend:{uuid} | broadcast:{uuid}; one logical send per key.
            $table->string('dedupe_key', 191)->unique();
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('audience', 50)->default('All');
            $table->string('title');
            $table->text('excerpt');
            $table->string('slug');
            $table->string('status', 30)->default('preparing');
            $table->unsignedInteger('recipients_total')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['announcement_id', 'kind', 'status']);
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('announcement_dispatches')->cascadeOnDelete();
            $table->string('recipient_type', 20);
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel', 20);
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('manual_retries')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->unique(['dispatch_id', 'recipient_type', 'recipient_id', 'channel'], 'announcement_deliveries_recipient_unique');
            $table->index(['dispatch_id', 'status', 'next_attempt_at'], 'announcement_deliveries_work_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcement_dispatches');
    }
};
