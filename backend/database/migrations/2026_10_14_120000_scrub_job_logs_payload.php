<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical rows stored the full serialized job, which can include
     * password-reset and email-verification tokens. Irreversible by design.
     */
    public function up(): void
    {
        if (Schema::hasTable('job_logs') && Schema::hasColumn('job_logs', 'payload')) {
            // Bounded batches keep each transaction and its row locks short on large tables.
            DB::table('job_logs')->whereNotNull('payload')->select('id')->orderBy('id')
                ->chunkById(1000, function ($rows): void {
                    DB::table('job_logs')->whereIn('id', $rows->pluck('id'))->update(['payload' => null]);
                });
        }
    }

    public function down(): void
    {
        //
    }
};
