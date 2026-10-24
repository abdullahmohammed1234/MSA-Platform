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
            if (!Schema::hasColumn('ems_events', 'show_volunteer_button')) {
                $table->boolean('show_volunteer_button')->default(false)->after('show_remaining_tickets');
            }
            if (!Schema::hasColumn('ems_events', 'volunteer_url')) {
                $table->string('volunteer_url', 500)->nullable()->after('show_volunteer_button');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('ems_events', 'volunteer_url')) {
                $drops[] = 'volunteer_url';
            }
            if (Schema::hasColumn('ems_events', 'show_volunteer_button')) {
                $drops[] = 'show_volunteer_button';
            }
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
