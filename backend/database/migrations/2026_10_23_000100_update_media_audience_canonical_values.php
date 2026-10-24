<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'audience')) {
            // Normalize legacy audience values to canonical representations
            DB::table('media')
                ->where('audience', 'male')
                ->update(['audience' => 'brothers']);

            DB::table('media')
                ->where('audience', 'female')
                ->update(['audience' => 'sisters']);

            DB::table('media')
                ->whereNull('audience')
                ->orWhereNotIn('audience', ['brothers', 'sisters', 'both'])
                ->update(['audience' => 'both']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'audience')) {
            DB::table('media')
                ->where('audience', 'brothers')
                ->update(['audience' => 'male']);

            DB::table('media')
                ->where('audience', 'sisters')
                ->update(['audience' => 'female']);
        }
    }
};
