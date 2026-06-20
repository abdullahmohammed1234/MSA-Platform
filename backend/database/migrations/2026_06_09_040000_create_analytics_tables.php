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
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('started_at')->useCurrent()->index();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->unsigned()->nullable(); // in seconds
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->onDelete('cascade');
            $table->string('module')->index(); // e.g. website, academy, events, certificates
            $table->string('event_type')->index(); // e.g. page_view, click, submission, award
            $table->string('event_name')->index(); // e.g. course_completed, quiz_submitted
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key')->index();
            $table->double('metric_value');
            $table->string('period')->index(); // e.g. daily, weekly, monthly, overall
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });

        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('type'); // e.g. website, academy, events, certificates
            $table->json('filters')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('analytics_metrics');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sessions');
    }
};
