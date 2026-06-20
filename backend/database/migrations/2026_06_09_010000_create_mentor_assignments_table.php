<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mentor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status')->default('active'); // active, inactive, completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['mentor_id', 'student_id']);
            $table->index('mentor_id');
            $table->index('student_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_assignments');
    }
};
