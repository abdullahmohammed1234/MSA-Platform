<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Safely and idempotently ensure Jumu'ah is registered as the eighth canonical department.
     */
    public function up(): void
    {
        Department::firstOrCreate(
            ['slug' => 'jumuah'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "Jumu'ah",
                'description' => 'Friday congregational prayer services, khateeb scheduling, campus logistics, and prayer hall management.',
                'color' => '#059669',
                'display_order' => 8,
                'is_active' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     * Soft-deactivate rather than destructive delete to preserve data integrity.
     */
    public function down(): void
    {
        $dept = Department::where('slug', 'jumuah')->first();
        if ($dept && $dept->projects()->count() === 0 && $dept->memberships()->count() === 0) {
            $dept->delete();
        }
    }
};
