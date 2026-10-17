<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64)->index();
            $table->string('subject', 255);
            $table->string('status', 32)->default('open')->index();
            $table->string('priority', 32)->default('normal');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->useCurrent()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('inquiry_id')->constrained('support_inquiries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_staff_reply')->default(false)->index();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'created_at']);
        });

        // Seed 'manage_support' permission if permissions table exists
        if (Schema::hasTable('permissions')) {
            $exists = DB::table('permissions')->where('slug', 'manage_support')->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => 'Manage Member Support',
                    'slug' => 'manage_support',
                    'module' => 'Support',
                    'uuid' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_inquiries');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('slug', 'manage_support')->delete();
        }
    }
};
