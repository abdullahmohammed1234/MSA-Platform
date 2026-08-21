<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable Square order identity on EMS orders so ingest, webhooks, and
 * reconciliation converge on one EMS sale per Square order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_orders', function (Blueprint $table) {
            $table->string('provider_order_id', 191)->nullable()->after('source_channel');
            $table->unique('provider_order_id', 'ems_orders_provider_order_id_uq');
        });
    }

    public function down(): void
    {
        Schema::table('ems_orders', function (Blueprint $table) {
            $table->dropUnique('ems_orders_provider_order_id_uq');
            $table->dropColumn('provider_order_id');
        });
    }
};
