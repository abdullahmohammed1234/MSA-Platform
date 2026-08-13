<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive media library enhancements:
 * - media_categories taxonomy for CMS uploads
 * - optional display_name (custom title) on media rows
 * - optional category_id FK (nullable so existing rows stay valid)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->timestamps();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('filename');
            $table->foreignId('category_id')
                ->nullable()
                ->after('display_name')
                ->constrained('media_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('display_name');
        });

        Schema::dropIfExists('media_categories');
    }
};
