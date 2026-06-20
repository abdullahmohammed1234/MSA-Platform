<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Milestone;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CertificationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed New Permissions
        $newPermissions = [
            ['name' => 'Manage Certificate Templates', 'slug' => 'manage_certificate_templates', 'module' => 'Academy', 'description' => 'Create, edit, and delete certificate visual layouts.'],
            ['name' => 'Manage Achievements', 'slug' => 'manage_achievements', 'module' => 'Academy', 'description' => 'Manage gamified achievements and rewards.'],
            ['name' => 'Manage Badges', 'slug' => 'manage_badges', 'module' => 'Academy', 'description' => 'Manage visual badge awards.'],
        ];

        foreach ($newPermissions as $perm) {
            $permission = Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $perm['name'],
                    'module' => $perm['module'],
                    'description' => $perm['description'],
                ]
            );

            // Sync with Super Admin & Dawah Coordinator roles
            $superAdmin = Role::where('slug', 'super-admin')->first();
            if ($superAdmin) {
                $superAdmin->permissions()->syncWithoutDetaching([$permission->id]);
            }

            $coordinator = Role::where('slug', 'dawah-coordinator')->first();
            if ($coordinator) {
                $coordinator->permissions()->syncWithoutDetaching([$permission->id]);
            }

            $admin = Role::where('slug', 'admin')->first();
            if ($admin) {
                $admin->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }

        // 2. Default Certificate Template
        $template = CertificateTemplate::firstOrCreate(
            ['name' => 'Standard Course Completion'],
            [
                'uuid' => (string) Str::uuid(),
                'title_template' => 'Certificate of Completion',
                'description_template' => 'For successfully completing the program: [course_title]',
                'layout' => 'landscape',
                'branding' => [
                    'primary_color' => '#0F172A', // Slate 900
                    'secondary_color' => '#10B981', // Emerald 500
                    'logo_url' => '/assets/msa-logo-gold.svg',
                ],
                'signatures' => [
                    ['name' => 'Dr. Abdullah Mohammad', 'title' => 'Dawah Academy Director', 'image_path' => null],
                    ['name' => 'Sr. Fatima Al-Zahra', 'title' => 'Academy Coordinator', 'image_path' => null],
                ],
                'background_asset' => null,
                'status' => 'active',
            ]
        );

        // 3. Setup Certificates for existing courses if any
        $courses = Course::all();
        foreach ($courses as $course) {
            $cert = Certificate::firstOrCreate(
                ['course_id' => $course->id, 'type' => 'course'],
                [
                    'uuid' => (string) Str::uuid(),
                    'certificate_template_id' => $template->id,
                    'title' => "Certificate of Completion - {$course->title}",
                    'description' => "Awarded to the student who has completed all modules, lessons, and quiz requirements for: {$course->title}.",
                ]
            );

            // Seed default requirements
            CertificateRequirement::firstOrCreate(
                ['certificate_id' => $cert->id, 'type' => 'lesson_completion'],
                ['uuid' => (string) Str::uuid(), 'parameters' => ['percentage' => 100]]
            );

            CertificateRequirement::firstOrCreate(
                ['certificate_id' => $cert->id, 'type' => 'passing_score'],
                ['uuid' => (string) Str::uuid(), 'parameters' => []]
            );
        }

        // 4. Setup Certificates for learning paths if any
        $paths = LearningPath::all();
        foreach ($paths as $path) {
            $cert = Certificate::firstOrCreate(
                ['learning_path_id' => $path->id, 'type' => 'learning_path'],
                [
                    'uuid' => (string) Str::uuid(),
                    'certificate_template_id' => $template->id,
                    'title' => "{$path->title} Path Certification",
                    'description' => "Awarded for completing the entire {$path->title} learning path and passing all assessments.",
                ]
            );

            CertificateRequirement::firstOrCreate(
                ['certificate_id' => $cert->id, 'type' => 'course_completion'],
                ['uuid' => (string) Str::uuid(), 'parameters' => []]
            );

            CertificateRequirement::firstOrCreate(
                ['certificate_id' => $cert->id, 'type' => 'average_score'],
                ['uuid' => (string) Str::uuid(), 'parameters' => ['min_average_score' => 75]]
            );
        }

        // 5. Default Achievements
        $achievements = [
            [
                'title' => 'First Step',
                'slug' => 'first-step',
                'description' => 'Complete your first lesson at the Dawah Academy.',
                'type' => 'participation',
                'points' => 50,
                'criteria_type' => 'lessons_count',
                'criteria_value' => ['threshold' => 1],
            ],
            [
                'title' => 'Knowledge Seeker',
                'slug' => 'knowledge-seeker',
                'description' => 'Successfully complete 10 academy lessons.',
                'type' => 'completion',
                'points' => 100,
                'criteria_type' => 'lessons_count',
                'criteria_value' => ['threshold' => 10],
            ],
            [
                'title' => 'Consistent Scholar',
                'slug' => 'consistent-scholar',
                'description' => 'Successfully complete 25 academy lessons.',
                'type' => 'completion',
                'points' => 250,
                'criteria_type' => 'lessons_count',
                'criteria_value' => ['threshold' => 25],
            ],
            [
                'title' => 'Academy Graduate',
                'slug' => 'academy-graduate',
                'description' => 'Complete a full academy course and earn a certificate.',
                'type' => 'completion',
                'points' => 500,
                'criteria_type' => 'courses_count',
                'criteria_value' => ['threshold' => 1],
            ],
            [
                'title' => 'Elite Leader',
                'slug' => 'elite-leader',
                'description' => 'Complete 5 academy courses.',
                'type' => 'completion',
                'points' => 1000,
                'criteria_type' => 'courses_count',
                'criteria_value' => ['threshold' => 5],
            ],
        ];

        foreach ($achievements as $ach) {
            Achievement::firstOrCreate(
                ['slug' => $ach['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $ach['title'],
                    'description' => $ach['description'],
                    'type' => $ach['type'],
                    'points' => $ach['points'],
                    'criteria_type' => $ach['criteria_type'],
                    'criteria_value' => $ach['criteria_value'],
                ]
            );
        }

        // 6. Default Badges
        $badges = [
            [
                'name' => 'First Course Completed',
                'slug' => 'first-course-completed',
                'description' => 'Unlocked when you complete your very first course.',
                'image_path' => '/assets/badges/badge-first-course.svg',
                'criteria_type' => 'first_course_completed',
                'criteria_value' => [],
            ],
            [
                'name' => 'Quiz Master',
                'slug' => 'quiz-master',
                'description' => 'Pass at least 5 different academy quizzes.',
                'image_path' => '/assets/badges/badge-quiz-master.svg',
                'criteria_type' => 'quiz_master',
                'criteria_value' => ['threshold' => 5],
            ],
            [
                'name' => 'Perfect Score',
                'slug' => 'perfect-score',
                'description' => 'Score 100% on any quiz.',
                'image_path' => '/assets/badges/badge-perfect-score.svg',
                'criteria_type' => 'perfect_score',
                'criteria_value' => [],
            ],
            [
                'name' => 'Consistent Learner',
                'slug' => 'consistent-learner',
                'description' => 'Complete 10 lessons in the academy.',
                'image_path' => '/assets/badges/badge-consistent.svg',
                'criteria_type' => 'consistent_learner',
                'criteria_value' => ['threshold' => 10],
            ],
            [
                'name' => 'Volunteer Graduate',
                'slug' => 'volunteer-graduate',
                'description' => 'Complete the Volunteer Training Learning Path.',
                'image_path' => '/assets/badges/badge-volunteer.svg',
                'criteria_type' => 'volunteer_graduate',
                'criteria_value' => [],
            ],
        ];

        foreach ($badges as $bdg) {
            Badge::firstOrCreate(
                ['slug' => $bdg['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $bdg['name'],
                    'description' => $bdg['description'],
                    'image_path' => $bdg['image_path'],
                    'criteria_type' => $bdg['criteria_type'],
                    'criteria_value' => $bdg['criteria_value'],
                ]
            );
        }

        // 7. Default Milestones
        $milestones = [
            ['name' => '1 Course Completed', 'slug' => '1-course', 'description' => 'Complete 1 course.', 'type' => 'courses_completed', 'threshold' => 1],
            ['name' => '5 Courses Completed', 'slug' => '5-courses', 'description' => 'Complete 5 courses.', 'type' => 'courses_completed', 'threshold' => 5],
            ['name' => '10 Lessons Completed', 'slug' => '10-lessons', 'description' => 'Complete 10 lessons.', 'type' => 'lessons_completed', 'threshold' => 10],
            ['name' => '25 Lessons Completed', 'slug' => '25-lessons', 'description' => 'Complete 25 lessons.', 'type' => 'lessons_completed', 'threshold' => 25],
            ['name' => '100% Learning Path Completed', 'slug' => 'path-completed-milestone', 'description' => 'Complete a full learning path.', 'type' => 'paths_completed', 'threshold' => 1],
        ];

        foreach ($milestones as $mls) {
            Milestone::firstOrCreate(
                ['slug' => $mls['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $mls['name'],
                    'description' => $mls['description'],
                    'type' => $mls['type'],
                    'threshold' => $mls['threshold'],
                ]
            );
        }
    }
}
