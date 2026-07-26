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
        Schema::table('ems_events', function (Blueprint $table) {
            $table->string('organizer_name', 255)->nullable()->after('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropColumn('organizer_name');
        });
    }
};
