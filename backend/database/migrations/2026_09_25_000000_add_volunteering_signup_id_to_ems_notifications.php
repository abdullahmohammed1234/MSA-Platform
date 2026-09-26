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
        if (Schema::hasTable('ems_notifications') && ! Schema::hasColumn('ems_notifications', 'volunteering_signup_id')) {
            Schema::table('ems_notifications', function (Blueprint $table) {
                $table->foreignId('volunteering_signup_id')
                    ->nullable()
                    ->after('registration_id')
                    ->constrained('volunteering_signups')
                    ->nullOnDelete();

                $table->index('volunteering_signup_id', 'ems_notifications_vol_signup_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ems_notifications') && Schema::hasColumn('ems_notifications', 'volunteering_signup_id')) {
            Schema::table('ems_notifications', function (Blueprint $table) {
                $table->dropForeign(['volunteering_signup_id']);
                $table->dropIndex('ems_notifications_vol_signup_idx');
                $table->dropColumn('volunteering_signup_id');
            });
        }
    }
};
