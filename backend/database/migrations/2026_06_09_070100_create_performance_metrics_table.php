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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('method', 10);
            $table->integer('duration_ms')->unsigned();
            $table->integer('db_queries_count')->unsigned()->default(0);
            $table->integer('db_queries_time_ms')->unsigned()->default(0);
            $table->double('memory_mb')->unsigned()->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
