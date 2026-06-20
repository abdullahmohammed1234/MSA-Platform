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
        Schema::table('user_activities', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['severity', 'created_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['slug']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['severity', 'created_at']);
        });

        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
