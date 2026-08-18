<?php

use Database\Seeders\Ems\EmsEventCategorySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Fold the original MSA event taxonomy down to Social, Educational/Halaqahs, and Other.
 *
 * Does not insert categories into an empty table (so tests that build their own
 * taxonomy stay isolated). Existing installs are remapped in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        EmsEventCategorySeeder::refreshExistingCanonical();
        EmsEventCategorySeeder::retireLegacyCategories();
    }

    public function down(): void
    {
        // Data migration — original category rows are not restored.
    }
};
