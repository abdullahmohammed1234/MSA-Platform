<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_registrations', function (Blueprint $table) {
            $table->timestamp('digested_at')->nullable()->after('created_at');
            $table->uuid('digest_batch')->nullable()->after('digested_at');
            $table->index(['digested_at', 'created_at'], 'volunteer_registrations_digest_index');
        });

        // The old digest ran daily at 23:55 and sent that day's rows created so far,
        // so only rows up to its most recent run were delivered. Later rows are
        // left for the new claim-based digest.
        $lastLegacyRun = now()->setTime(23, 55);
        if ($lastLegacyRun->isFuture()) {
            $lastLegacyRun->subDay();
        }

        DB::table('volunteer_registrations')
            ->where('created_at', '<=', $lastLegacyRun)
            ->update(['digested_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('volunteer_registrations', function (Blueprint $table) {
            $table->dropIndex('volunteer_registrations_digest_index');
            $table->dropColumn(['digested_at', 'digest_batch']);
        });
    }
};
