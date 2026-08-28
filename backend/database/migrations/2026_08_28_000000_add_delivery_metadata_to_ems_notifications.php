<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_notifications', function (Blueprint $table) {
            $table->string('provider_message_id', 255)->nullable()->after('queue_status');
            $table->timestamp('alert_sent_at')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ems_notifications', function (Blueprint $table) {
            $table->dropColumn(['provider_message_id', 'alert_sent_at']);
        });
    }
};
