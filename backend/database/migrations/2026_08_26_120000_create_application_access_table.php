<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('application'); // main-website, cms, dawah-academy, dams, ems, admin-portal
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'application']);
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            $table->index('application');
        });

        // Seed CMS and DAMS access for existing ordinary users holding those permissions to avoid breaking access
        $cmsPermissions = [
            'manage_homepage', 'manage_announcements', 'manage_team', 
            'manage_resources', 'manage_media', 'view_analytics', 
            'view_reports', 'manage_analytics', 'export_analytics'
        ];

        $damsPermissions = [
            'manage_courses', 'manage_modules', 'manage_lessons', 
            'manage_quizzes', 'manage_learning_paths', 'manage_mentors', 
            'manage_students', 'view_progress', 'manage_achievements', 
            'manage_badges', 'manage_settings', 'manage_notifications', 
            'manage_discussions', 'view_analytics', 'view_reports', 
            'manage_analytics', 'export_analytics'
        ];

        // We run seeder dynamically inside migration
        try {
            $users = User::all();
            foreach ($users as $user) {
                // Determine if they hold any of the CMS capabilities
                $hasCms = false;
                foreach ($cmsPermissions as $p) {
                    if ($user->permissions()->where('slug', $p)->exists() ||
                        $user->roles()->whereHas('permissions', fn($q) => $q->where('slug', $p))->exists()) {
                        $hasCms = true;
                        break;
                    }
                }

                if ($hasCms) {
                    DB::table('application_access')->insertOrIgnore([
                        'user_id' => $user->id,
                        'application' => 'cms',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Determine if they hold any of the DAMS capabilities
                $hasDams = false;
                foreach ($damsPermissions as $p) {
                    if ($user->permissions()->where('slug', $p)->exists() ||
                        $user->roles()->whereHas('permissions', fn($q) => $q->where('slug', $p))->exists()) {
                        $hasDams = true;
                        break;
                    }
                }

                if ($hasDams) {
                    DB::table('application_access')->insertOrIgnore([
                        'user_id' => $user->id,
                        'application' => 'dams',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log warning or continue safely if users table is not populated/synced
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_access');
    }
};
