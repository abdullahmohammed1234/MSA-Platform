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
            if (! Schema::hasColumn('event_registrations', 'registration_group_id')) {
                $table->uuid('registration_group_id')->nullable()->after('uuid')->index();
            }

            if (! Schema::hasColumn('event_registrations', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            if (! Schema::hasColumn('event_registrations', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('status');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE event_registrations MODIFY student_id VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_registrations ALTER COLUMN student_id DROP NOT NULL');
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
            $columns = collect(['registration_group_id', 'phone', 'checked_in_at'])
                ->filter(fn (string $column) => Schema::hasColumn('event_registrations', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE event_registrations MODIFY student_id VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_registrations ALTER COLUMN student_id SET NOT NULL');
        }
    }
};
