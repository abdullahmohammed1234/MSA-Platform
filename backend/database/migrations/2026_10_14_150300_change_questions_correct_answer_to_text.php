<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Question::correct_answer uses the encrypted:array cast, so the stored value is
     * ciphertext, not JSON. MariaDB enforces JSON_VALID() on json columns (MySQL 8
     * validates natively), which rejected every question insert.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('correct_answer')->change();
        });
    }

    public function down(): void
    {
        // Irreversible on purpose: existing ciphertext can never satisfy a json column.
    }
};
