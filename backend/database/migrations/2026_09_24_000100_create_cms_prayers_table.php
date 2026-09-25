<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_prayers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->default('jumuah')->index(); // 'daily' or 'jumuah'
            $table->string('title');
            $table->string('campus', 64)->nullable()->index(); // Burnaby, Surrey, Vancouver
            $table->string('day', 32)->nullable(); // e.g. Friday
            $table->boolean('is_enabled')->default(true)->index();
            $table->string('fajr_time', 32)->nullable();
            $table->string('dhuhr_time', 32)->nullable();
            $table->string('asr_time', 32)->nullable();
            $table->string('maghrib_time', 32)->nullable();
            $table->string('isha_time', 32)->nullable();
            $table->string('khutbah_time', 32)->nullable();
            $table->string('prayer_time', 32)->nullable();
            $table->string('location')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->json('timings_json')->nullable();
            $table->integer('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_prayers');
    }
};
