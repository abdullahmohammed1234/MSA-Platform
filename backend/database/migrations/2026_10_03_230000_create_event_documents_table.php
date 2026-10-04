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
        Schema::create('event_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ems_events')->cascadeOnDelete();
            $table->string('uuid', 36)->unique();
            $table->string('name');
            $table->string('document_type', 50)->default('other');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('storage_disk', 50);
            $table->string('storage_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('file_size');
            $table->string('access_token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('uuid');
            $table->index('access_token_hash');
            $table->index('is_active');
            $table->index(['event_id', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_documents');
    }
};
