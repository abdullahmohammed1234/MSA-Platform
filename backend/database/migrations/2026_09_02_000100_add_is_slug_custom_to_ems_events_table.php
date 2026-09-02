<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->boolean('is_slug_custom')->default(false)->after('slug');
        });

        // Initialize mode for any existing events based on title match
        $events = DB::table('ems_events')->get(['id', 'name', 'slug']);
        foreach ($events as $event) {
            $expectedBase = Str::slug($event->name);
            if (! empty($expectedBase) && ($event->slug === $expectedBase || str_starts_with($event->slug, $expectedBase . '-'))) {
                // Matches expected auto-generated slug pattern
                continue;
            }

            DB::table('ems_events')
                ->where('id', $event->id)
                ->update(['is_slug_custom' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('ems_events', function (Blueprint $table) {
            $table->dropColumn('is_slug_custom');
        });
    }
};
