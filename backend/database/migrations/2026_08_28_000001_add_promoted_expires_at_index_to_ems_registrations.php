<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->index(['status', 'promoted_expires_at'], 'ems_regs_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ems_registrations', function (Blueprint $table) {
            $table->dropIndex('ems_regs_status_expiry_idx');
        });
    }
};
