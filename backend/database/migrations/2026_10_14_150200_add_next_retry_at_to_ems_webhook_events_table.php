<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_webhook_events', function (Blueprint $table) {
            $table->timestamp('next_retry_at')->nullable()->after('last_attempt_at');
            $table->index(['provider', 'status', 'next_retry_at'], 'ems_webhook_events_retry_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('ems_webhook_events', function (Blueprint $table) {
            $table->dropIndex('ems_webhook_events_retry_due_index');
            $table->dropColumn('next_retry_at');
        });
    }
};
