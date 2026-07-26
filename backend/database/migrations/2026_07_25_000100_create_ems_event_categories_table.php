<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EMS event category taxonomy.
 *
 * Categories are database-driven so the frontend never hard-codes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_event_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();

            // Hex swatch used by the UI so category colours stay data-driven.
            $table->string('color', 7)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'ems_categories_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ems_event_categories');
    }
};
