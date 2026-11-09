<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ems_notifications') && ! Schema::hasColumn('ems_notifications', 'alert_claimed_at')) {
            Schema::table('ems_notifications', function (Blueprint $table) {
                $table->timestamp('alert_claimed_at')->nullable()->after('alert_sent_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ems_notifications') && Schema::hasColumn('ems_notifications', 'alert_claimed_at')) {
            Schema::table('ems_notifications', function (Blueprint $table) {
                $table->dropColumn('alert_claimed_at');
            });
        }
    }
};
