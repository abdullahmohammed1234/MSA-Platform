<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('volunteering_signups')) {
            Schema::table('volunteering_signups', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_vms_signups_status_created');
                $table->index(['attendance_status', 'attended_at'], 'idx_vms_signups_attendance');
                $table->index(['user_id', 'email'], 'idx_vms_signups_user_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('volunteering_signups')) {
            Schema::table('volunteering_signups', function (Blueprint $table) {
                $table->dropIndex('idx_vms_signups_status_created');
                $table->dropIndex('idx_vms_signups_attendance');
                $table->dropIndex('idx_vms_signups_user_email');
            });
        }
    }
};
