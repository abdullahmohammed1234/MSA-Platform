<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->string('attendee_email', 255)->nullable()->change();
        });

        Schema::table('ems_orders', function (Blueprint $table) {
            $table->string('buyer_email', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->string('attendee_email', 255)->nullable(false)->change();
        });

        Schema::table('ems_orders', function (Blueprint $table) {
            $table->string('buyer_email', 255)->nullable(false)->change();
        });
    }
};
