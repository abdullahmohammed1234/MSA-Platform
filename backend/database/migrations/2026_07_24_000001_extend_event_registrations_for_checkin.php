<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->uuid('registration_group_id')->nullable()->after('uuid')->index();
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('checked_in_at')->nullable()->after('status');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE event_registrations MODIFY student_id VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_registrations ALTER COLUMN student_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite does not enforce NOT NULL changes the same way; leave as-is for tests.
        }

        DB::table('event_registrations')
            ->where('status', 'confirmed')
            ->update(['status' => 'registered']);
    }

    public function down(): void
    {
        DB::table('event_registrations')
            ->whereIn('status', ['registered', 'attending'])
            ->update(['status' => 'confirmed']);

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['registration_group_id', 'phone', 'checked_in_at']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE event_registrations MODIFY student_id VARCHAR(255) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_registrations ALTER COLUMN student_id SET NOT NULL');
        }
    }
};
