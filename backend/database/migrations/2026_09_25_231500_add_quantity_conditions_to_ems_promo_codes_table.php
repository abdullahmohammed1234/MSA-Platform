<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_promo_codes', function (Blueprint $table) {
            $table->unsignedInteger('min_quantity')->nullable()->after('minimum_purchase');
            $table->unsignedInteger('max_quantity')->nullable()->after('min_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('ems_promo_codes', function (Blueprint $table) {
            $table->dropColumn(['min_quantity', 'max_quantity']);
        });
    }
};
