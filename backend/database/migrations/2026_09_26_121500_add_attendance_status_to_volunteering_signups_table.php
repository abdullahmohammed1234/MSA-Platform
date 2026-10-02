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
        Schema::table('volunteering_signups', function (Blueprint $table) {
            if (!Schema::hasColumn('volunteering_signups', 'attendance_status')) {
                $table->string('attendance_status', 32)->default('not_marked')->after('status')->index();
            }
            if (!Schema::hasColumn('volunteering_signups', 'attended_at')) {
                $table->timestamp('attended_at')->nullable()->after('attendance_status');
            }

            $table->index(['opportunity_id', 'attendance_status', 'status'], 'vol_signups_opp_att_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteering_signups', function (Blueprint $table) {
            $table->dropIndex('vol_signups_opp_att_status_idx');
            $table->dropColumn(['attendance_status', 'attended_at']);
        });
    }
};
