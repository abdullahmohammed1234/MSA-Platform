<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fingerprint + version for Square Payment Link reuse vs replacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_payments', function (Blueprint $table) {
            $table->char('checkout_details_hash', 64)->nullable()->after('checkout_expires_at');
            $table->unsignedInteger('checkout_version')->default(1)->after('checkout_details_hash');
        });
    }

    public function down(): void
    {
        Schema::table('ems_payments', function (Blueprint $table) {
            $table->dropColumn(['checkout_details_hash', 'checkout_version']);
        });
    }
};
