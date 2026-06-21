<?php

use App\Http\Controllers\Api\V1\AcademyController;
use App\Http\Controllers\Api\V1\AcademyDashboardController;
use App\Http\Controllers\Api\V1\AdminDiscussionController;
use App\Http\Controllers\Api\V1\MentorController;
use App\Http\Controllers\Api\V1\SimulationController;
use App\Http\Controllers\Api\V1\AdminAcademyController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebsiteController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 1. Auth routes (with rate limiting)
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/register', [RegisterController::class, 'register'])->name('api.auth.register');
            Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
            Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('api.auth.forgot-password');
            Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('api.auth.reset-password');
            Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->name('api.auth.verify-email');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/resend-verification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:auth')
                ->name('api.auth.resend-verification');
        });
    });

    // 2. Users routes
    Route::prefix('users')->middleware('auth:sanctum')->group(function () {
        Route::get('/me', [UserController::class, 'me'])->name('api.users.me');
        Route::put('/profile', [UserController::class, 'updateProfile'])->name('api.users.profile');
        Route::post('/academy-onboarding/complete', [UserController::class, 'completeAcademyOnboarding'])
            ->name('api.users.academy-onboarding.complete');
    });

    // 3. Academy routes (volunteers, mentors, and admins only)
    Route::prefix('academy')->middleware(['auth:sanctum', 'role:volunteer|mentor|admin|super-admin'])->group(function () {
        Route::get('/courses', [AcademyController::class, 'courses'])->name('api.academy.courses');
        Route::get('/courses/{id_or_slug}', [AcademyController::class, 'courseDetails'])->name('api.academy.courses.show');
        Route::post('/courses/{course}/enroll', [AcademyController::class, 'enroll'])->name('api.academy.courses.enroll');
        Route::post('/courses/{course}/lessons/{lesson}/complete', [AcademyController::class, 'completeLesson'])->name('api.academy.lessons.complete');
        Route::post('/courses/{course}/quizzes/{quiz}/submit', [AcademyController::class, 'submitQuiz'])->name('api.academy.quizzes.submit');
        Route::get('/learning-paths', [AcademyController::class, 'learningPaths'])->name('api.academy.learning-paths');
        Route::get('/achievements', [AcademyController::class, 'achievements'])->name('api.academy.achievements');
        Route::get('/badges', [AcademyController::class, 'badges'])->name('api.academy.badges');
        Route::get('/milestones', [AcademyController::class, 'milestones'])->name('api.academy.milestones');
        Route::get('/quiz-attempts', [AcademyController::class, 'quizAttempts'])->name('api.academy.quiz-attempts');
        Route::get('/dashboard', [AcademyDashboardController::class, 'index'])->name('api.academy.dashboard');
        Route::get('/resources', [AcademyController::class, 'resources'])->name('api.academy.resources');
    });

    Route::middleware(['auth:sanctum', 'role:volunteer|mentor|admin|super-admin'])->group(function () {
        Route::get('/mentor/dashboard', [MentorController::class, 'dashboard'])->middleware('role:mentor|admin|super-admin')->name('api.mentor.dashboard');
        Route::post('/mentor/submissions/{attempt}/grade', [MentorController::class, 'gradeSubmission'])->middleware('role:mentor|admin|super-admin')->name('api.mentor.submissions.grade');

        Route::prefix('simulations')->group(function () {
            Route::get('/scenarios', [SimulationController::class, 'scenarios'])->name('api.simulations.scenarios');
            Route::get('/scenarios/{scenarioId}', [SimulationController::class, 'scenario'])->name('api.simulations.scenarios.show');
            Route::get('/history', [SimulationController::class, 'history'])->name('api.simulations.history');
            Route::post('/sessions', [SimulationController::class, 'store'])->name('api.simulations.sessions.store');
            Route::patch('/sessions/{session}/bookmark', [SimulationController::class, 'updateBookmark'])->name('api.simulations.sessions.bookmark');
        });
    });

    // 4. Discussions & Dawah Schedule routes (Academy participants only)
    Route::middleware(['auth:sanctum', 'role:volunteer|mentor|admin|super-admin'])->group(function () {
        Route::get('/dawah-schedule', [\App\Http\Controllers\Api\V1\DawahScheduleController::class, 'index'])->name('api.dawah-schedule.index');
        Route::patch('/dawah-schedule', [\App\Http\Controllers\Api\V1\DawahScheduleController::class, 'update'])->name('api.dawah-schedule.update');

        Route::get('/discussion-categories', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'getCategories'])->name('api.discussions.categories');
        Route::get('/discussions', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'index'])->name('api.discussions.index');
        Route::get('/discussions/{threadId}/posts', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'getPosts'])->name('api.discussions.posts.index');
        Route::post('/discussions', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'storeThread'])->name('api.discussions.store');
        Route::post('/discussions/{threadId}/posts', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'storePost'])->name('api.discussions.posts.store');
        Route::post('/discussions/reactions', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'toggleReaction'])->name('api.discussions.reactions');
        Route::post('/discussions/bookmarks', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'toggleBookmark'])->name('api.discussions.bookmarks');
        Route::post('/discussions/reports', [\App\Http\Controllers\Api\V1\DiscussionController::class, 'storeReport'])->name('api.discussions.reports');
    });

    Route::prefix('website')->group(function () {
        Route::get('/homepage', [WebsiteController::class, 'homepage'])->name('api.website.homepage');
        Route::get('/announcements', [WebsiteController::class, 'announcements'])->name('api.website.announcements');
        Route::get('/events', [WebsiteController::class, 'events'])->name('api.website.events');
        Route::get('/events/registrations', [WebsiteController::class, 'myEventRegistrations'])->name('api.website.events.registrations');
        Route::post('/events/{eventId}/rsvp', [WebsiteController::class, 'submitEventRsvp'])->middleware('throttle:public_forms')->name('api.website.events.rsvp');
        Route::delete('/events/{eventId}/rsvp', [WebsiteController::class, 'cancelEventRsvp'])->middleware('throttle:public_forms')->name('api.website.events.rsvp.cancel');
        Route::get('/team', [WebsiteController::class, 'team'])->name('api.website.team');
        Route::get('/resources', [WebsiteController::class, 'resources'])->name('api.website.resources');
        Route::get('/media', [WebsiteController::class, 'media'])->name('api.website.media');
        Route::get('/sponsors', [WebsiteController::class, 'sponsors'])->name('api.website.sponsors');
        Route::get('/prayer-times', [WebsiteController::class, 'prayerTimes'])->name('api.website.prayer-times');
        Route::post('/contact', [WebsiteController::class, 'submitContact'])->middleware('throttle:public_forms')->name('api.website.contact');
        Route::post('/newsletter/subscribe', [WebsiteController::class, 'subscribeNewsletter'])->middleware('throttle:public_forms')->name('api.website.newsletter.subscribe');
        Route::post('/sponsors', [WebsiteController::class, 'submitSponsor'])->middleware('throttle:public_forms')->name('api.website.sponsors.submit');
        Route::post('/volunteer', [WebsiteController::class, 'submitVolunteer'])->middleware('throttle:public_forms')->name('api.website.volunteer.submit');
    });

    // 5. Admin routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:admin_api'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'index'])
            ->middleware('permission:view_analytics')
            ->name('api.admin.dashboard');

        Route::get('/users', [AdminController::class, 'users'])
            ->middleware('permission:manage_users')
            ->name('api.admin.users.index');
        
        // Admin Academy LMS routes
        Route::prefix('academy')->group(function () {
            Route::get('/analytics', [AdminAcademyController::class, 'analytics'])->middleware('permission:view_analytics')->name('api.admin.academy.analytics');

            Route::get('/courses', [AdminAcademyController::class, 'indexCourses'])->middleware('permission:manage_courses')->name('api.admin.courses.index');
            Route::post('/courses', [AdminAcademyController::class, 'storeCourse'])->middleware('permission:manage_courses')->name('api.admin.courses.store');
            Route::put('/courses/{course}', [AdminAcademyController::class, 'updateCourse'])->middleware('permission:manage_courses')->name('api.admin.courses.update');
            Route::delete('/courses/{course}', [AdminAcademyController::class, 'destroyCourse'])->middleware('permission:manage_courses')->name('api.admin.courses.destroy');

            Route::post('/modules', [AdminAcademyController::class, 'storeModule'])->middleware('permission:manage_modules')->name('api.admin.academy.modules.store');
            Route::put('/modules/{module}', [AdminAcademyController::class, 'updateModule'])->middleware('permission:manage_modules')->name('api.admin.academy.modules.update');
            Route::delete('/modules/{module}', [AdminAcademyController::class, 'destroyModule'])->middleware('permission:manage_modules')->name('api.admin.academy.modules.destroy');
            Route::post('/courses/{courseId}/modules/reorder', [AdminAcademyController::class, 'reorderModules'])->middleware('permission:manage_modules')->name('api.admin.academy.modules.reorder');

            Route::post('/lessons', [AdminAcademyController::class, 'storeLesson'])->middleware('permission:manage_lessons')->name('api.admin.academy.lessons.store');
            Route::put('/lessons/{lesson}', [AdminAcademyController::class, 'updateLesson'])->middleware('permission:manage_lessons')->name('api.admin.academy.lessons.update');
            Route::delete('/lessons/{lesson}', [AdminAcademyController::class, 'destroyLesson'])->middleware('permission:manage_lessons')->name('api.admin.academy.lessons.destroy');
            Route::post('/modules/{moduleId}/lessons/reorder', [AdminAcademyController::class, 'reorderLessons'])->middleware('permission:manage_lessons')->name('api.admin.academy.lessons.reorder');

            Route::get('/quizzes', [AdminAcademyController::class, 'indexQuizzes'])->middleware('permission:manage_quizzes')->name('api.admin.academy.quizzes.index');
            Route::post('/quizzes', [AdminAcademyController::class, 'storeQuiz'])->middleware('permission:manage_quizzes')->name('api.admin.academy.quizzes.store');
            Route::put('/quizzes/{quiz}', [AdminAcademyController::class, 'updateQuiz'])->middleware('permission:manage_quizzes')->name('api.admin.academy.quizzes.update');
            Route::delete('/quizzes/{quiz}', [AdminAcademyController::class, 'destroyQuiz'])->middleware('permission:manage_quizzes')->name('api.admin.academy.quizzes.destroy');

            Route::post('/questions', [AdminAcademyController::class, 'storeQuestion'])->middleware('permission:manage_quizzes')->name('api.admin.academy.questions.store');
            Route::put('/questions/{question}', [AdminAcademyController::class, 'updateQuestion'])->middleware('permission:manage_quizzes')->name('api.admin.academy.questions.update');
            Route::delete('/questions/{question}', [AdminAcademyController::class, 'destroyQuestion'])->middleware('permission:manage_quizzes')->name('api.admin.academy.questions.destroy');

            Route::get('/learning-paths', [AdminAcademyController::class, 'indexLearningPaths'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.index');
            Route::post('/learning-paths', [AdminAcademyController::class, 'storeLearningPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.store');
            Route::put('/learning-paths/{learningPath}', [AdminAcademyController::class, 'updateLearningPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.update');
            Route::delete('/learning-paths/{learningPath}', [AdminAcademyController::class, 'destroyLearningPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.destroy');
            Route::post('/learning-paths/{learningPath}/courses', [AdminAcademyController::class, 'assignCourseToPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.assign');
            Route::delete('/learning-paths/{learningPath}/courses/{course}', [AdminAcademyController::class, 'removeCourseFromPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.remove');
            Route::post('/learning-paths/{learningPath}/courses/reorder', [AdminAcademyController::class, 'reorderCoursesInPath'])->middleware('permission:manage_learning_paths')->name('api.admin.academy.learning-paths.reorder');

            // Admin Achievements CRUD
            Route::get('/achievements', [AdminAcademyController::class, 'indexAchievements'])->middleware('permission:manage_achievements')->name('api.admin.academy.achievements.index');
            Route::post('/achievements', [AdminAcademyController::class, 'storeAchievement'])->middleware('permission:manage_achievements')->name('api.admin.academy.achievements.store');
            Route::put('/achievements/{achievement}', [AdminAcademyController::class, 'updateAchievement'])->middleware('permission:manage_achievements')->name('api.admin.academy.achievements.update');
            Route::delete('/achievements/{achievement}', [AdminAcademyController::class, 'destroyAchievement'])->middleware('permission:manage_achievements')->name('api.admin.academy.achievements.destroy');

            // Admin Badges CRUD
            Route::get('/badges', [AdminAcademyController::class, 'indexBadges'])->middleware('permission:manage_badges')->name('api.admin.academy.badges.index');
            Route::post('/badges', [AdminAcademyController::class, 'storeBadge'])->middleware('permission:manage_badges')->name('api.admin.academy.badges.store');
            Route::put('/badges/{badge}', [AdminAcademyController::class, 'updateBadge'])->middleware('permission:manage_badges')->name('api.admin.academy.badges.update');
            Route::delete('/badges/{badge}', [AdminAcademyController::class, 'destroyBadge'])->middleware('permission:manage_badges')->name('api.admin.academy.badges.destroy');

            // Centralized Question Bank
            Route::get('/questions', [AdminAcademyController::class, 'indexQuestions'])->middleware('permission:manage_quizzes')->name('api.admin.academy.questions.index');

            // Student Management
            Route::get('/students', [AdminAcademyController::class, 'indexStudents'])->middleware('permission:manage_students')->name('api.admin.academy.students.index');
            Route::get('/students/{student}', [AdminAcademyController::class, 'showStudent'])->middleware('permission:manage_students')->name('api.admin.academy.students.show');
            Route::post('/students/{student}/suspend', [AdminAcademyController::class, 'suspendStudent'])->middleware('permission:manage_students')->name('api.admin.academy.students.suspend');
            Route::post('/students/{student}/reactivate', [AdminAcademyController::class, 'reactivateStudent'])->middleware('permission:manage_students')->name('api.admin.academy.students.reactivate');

            // Mentor Management & Assignments
            Route::get('/mentors', [AdminAcademyController::class, 'indexMentors'])->middleware('permission:manage_mentors')->name('api.admin.academy.mentors.index');
            Route::get('/mentors/{mentor}', [AdminAcademyController::class, 'showMentor'])->middleware('permission:manage_mentors')->name('api.admin.academy.mentors.show');
            Route::get('/assignments', [AdminAcademyController::class, 'indexAssignments'])->middleware('permission:manage_mentors')->name('api.admin.academy.assignments.index');
            Route::post('/assignments', [AdminAcademyController::class, 'storeAssignment'])->middleware('permission:manage_mentors')->name('api.admin.academy.assignments.store');
            Route::post('/assignments/bulk', [AdminAcademyController::class, 'bulkAssign'])->middleware('permission:manage_mentors')->name('api.admin.academy.assignments.bulk');
            Route::delete('/assignments/{mentorId}/{studentId}', [AdminAcademyController::class, 'destroyAssignment'])->middleware('permission:manage_mentors')->name('api.admin.academy.assignments.destroy');

            // Student Progress
            Route::get('/progress', [AdminAcademyController::class, 'indexProgress'])->middleware('permission:view_progress')->name('api.admin.academy.progress.index');

            Route::get('/discussions/reports', [AdminDiscussionController::class, 'reports'])->middleware('permission:manage_discussions')->name('api.admin.discussions.reports');
            Route::get('/discussions/threads', [AdminDiscussionController::class, 'threads'])->middleware('permission:manage_discussions')->name('api.admin.discussions.threads');
            Route::patch('/discussions/reports/{report}/resolve', [AdminDiscussionController::class, 'resolve'])->middleware('permission:manage_discussions')->name('api.admin.discussions.reports.resolve');
        });

        // Admin Notifications Management
        Route::prefix('notifications')->group(function () {
            Route::get('/logs', [\App\Http\Controllers\Api\V1\Admin\AdminNotificationController::class, 'logs'])
                ->middleware('permission:manage_notifications')
                ->name('api.admin.notifications.logs');
                
            Route::post('/resend/{logId}', [\App\Http\Controllers\Api\V1\Admin\AdminNotificationController::class, 'resend'])
                ->middleware('permission:send_notifications')
                ->name('api.admin.notifications.resend');
                
            Route::post('/broadcast', [\App\Http\Controllers\Api\V1\Admin\AdminNotificationController::class, 'broadcast'])
                ->middleware('permission:send_notifications')
                ->name('api.admin.notifications.broadcast');
                
            Route::get('/stats', [\App\Http\Controllers\Api\V1\Admin\AdminNotificationController::class, 'stats'])
                ->middleware('permission:manage_notifications')
                ->name('api.admin.notifications.stats');
        });

        // Role & Permission Management
        Route::get('/roles', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'indexRoles'])
            ->middleware('permission:manage_roles')
            ->name('api.admin.roles.index');
            
        Route::post('/roles', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'storeRole'])
            ->middleware('permission:manage_roles')
            ->name('api.admin.roles.store');
            
        Route::put('/roles/{role:uuid}', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'updateRole'])
            ->middleware('permission:manage_roles')
            ->name('api.admin.roles.update');
            
        Route::delete('/roles/{role:uuid}', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'destroyRole'])
            ->middleware('permission:manage_roles')
            ->name('api.admin.roles.destroy');
            
        Route::get('/permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'indexPermissions'])
            ->middleware('permission:manage_permissions')
            ->name('api.admin.permissions.index');
            
        Route::post('/users/{user:uuid}/roles', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'assignRolesToUser'])
            ->middleware('permission:manage_users')
            ->name('api.admin.users.roles.assign');
            
        Route::post('/users/{user:uuid}/permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'assignPermissionsToUser'])
            ->middleware('permission:manage_users')
            ->name('api.admin.users.permissions.assign');
            
        Route::get('/audit-logs', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'auditLogs'])
            ->middleware('permission:view_analytics')
            ->name('api.admin.audit-logs.index');

        // Security Dashboard
        Route::get('/security/dashboard', [\App\Http\Controllers\Api\V1\Admin\SecurityDashboardController::class, 'dashboard'])
            ->middleware('permission:view_security')
            ->name('api.admin.security.dashboard');

        // CMS Dashboard
        Route::get('/cms/dashboard', [\App\Http\Controllers\Admin\CMS\CmsDashboardController::class, 'index'])
            ->middleware('permission:view_analytics')
            ->name('api.admin.cms.dashboard');

        // CMS Homepage
        Route::get('/cms/homepage', [\App\Http\Controllers\Admin\CMS\HomepageController::class, 'index'])
            ->middleware('permission:manage_homepage')
            ->name('api.admin.cms.homepage.index');
        Route::put('/cms/homepage/{key}', [\App\Http\Controllers\Admin\CMS\HomepageController::class, 'update'])
            ->middleware('permission:manage_homepage')
            ->name('api.admin.cms.homepage.update');

        // CMS Announcements
        Route::get('/cms/announcements', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'index'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.index');
        Route::post('/cms/announcements', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'store'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.store');
        Route::get('/cms/announcements/{uuid}', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'show'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.show');
        Route::put('/cms/announcements/{uuid}', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'update'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.update');
        Route::delete('/cms/announcements/{uuid}', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'destroy'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.destroy');
        Route::get('/cms/announcements/{uuid}/revisions', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'revisions'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.revisions');
        Route::post('/cms/announcements/{uuid}/rollback', [\App\Http\Controllers\Admin\CMS\AnnouncementController::class, 'rollback'])
            ->middleware('permission:manage_announcements')
            ->name('api.admin.cms.announcements.rollback');

        // CMS Events
        Route::get('/cms/events', [\App\Http\Controllers\Admin\CMS\EventController::class, 'index'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.index');
        Route::post('/cms/events', [\App\Http\Controllers\Admin\CMS\EventController::class, 'store'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.store');
        Route::get('/cms/events/{uuid}', [\App\Http\Controllers\Admin\CMS\EventController::class, 'show'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.show');
        Route::put('/cms/events/{uuid}', [\App\Http\Controllers\Admin\CMS\EventController::class, 'update'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.update');
        Route::delete('/cms/events/{uuid}', [\App\Http\Controllers\Admin\CMS\EventController::class, 'destroy'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.destroy');
        Route::get('/cms/events/{uuid}/revisions', [\App\Http\Controllers\Admin\CMS\EventController::class, 'revisions'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.revisions');
        Route::get('/cms/events/{uuid}/registrations', [\App\Http\Controllers\Admin\CMS\EventController::class, 'registrations'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.registrations');
        Route::post('/cms/events/{uuid}/rollback', [\App\Http\Controllers\Admin\CMS\EventController::class, 'rollback'])
            ->middleware('permission:manage_events')
            ->name('api.admin.cms.events.rollback');

        // CMS Team
        Route::get('/cms/team', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'index'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.index');
        Route::post('/cms/team', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'store'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.store');
        Route::get('/cms/team/{uuid}', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'show'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.show');
        Route::put('/cms/team/{uuid}', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'update'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.update');
        Route::delete('/cms/team/{uuid}', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'destroy'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.destroy');
        Route::post('/cms/team/reorder', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'reorder'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.reorder');
        Route::get('/cms/team/{uuid}/revisions', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'revisions'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.revisions');
        Route::post('/cms/team/{uuid}/rollback', [\App\Http\Controllers\Admin\CMS\TeamController::class, 'rollback'])
            ->middleware('permission:manage_team')
            ->name('api.admin.cms.team.rollback');

        // CMS Resources
        Route::get('/cms/resources', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'index'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.index');
        Route::post('/cms/resources', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'store'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.store');
        Route::get('/cms/resources/{uuid}', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'show'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.show');
        Route::put('/cms/resources/{uuid}', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'update'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.update');
        Route::delete('/cms/resources/{uuid}', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'destroy'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.destroy');
        Route::get('/cms/resources/{uuid}/revisions', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'revisions'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.revisions');
        Route::post('/cms/resources/{uuid}/rollback', [\App\Http\Controllers\Admin\CMS\ResourceController::class, 'rollback'])
            ->middleware('permission:manage_resources')
            ->name('api.admin.cms.resources.rollback');

        // CMS Media
        Route::get('/cms/media', [\App\Http\Controllers\Admin\CMS\MediaController::class, 'index'])
            ->middleware('permission:manage_media')
            ->name('api.admin.cms.media.index');
        Route::post('/cms/media', [\App\Http\Controllers\Admin\CMS\MediaController::class, 'store'])
            ->middleware('permission:manage_media')
            ->name('api.admin.cms.media.store');
        Route::delete('/cms/media/{uuid}', [\App\Http\Controllers\Admin\CMS\MediaController::class, 'destroy'])
            ->middleware('permission:manage_media')
            ->name('api.admin.cms.media.destroy');

        // System Queue & Scheduler management
        Route::prefix('system')->group(function () {
            Route::get('/queues', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'queues'])->name('api.admin.system.queues');
            Route::get('/jobs', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'jobLogs'])->name('api.admin.system.jobs');
            Route::post('/queues/clear', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'clearQueue'])->name('api.admin.system.queues.clear');
            
            Route::get('/jobs/failed', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'failedJobs'])->name('api.admin.system.jobs.failed');
            Route::post('/jobs/failed/{uuid}/retry', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'retryFailedJob'])->name('api.admin.system.jobs.failed.retry');
            Route::post('/jobs/failed/retry-all', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'retryAllFailedJobs'])->name('api.admin.system.jobs.failed.retry-all');
            Route::delete('/jobs/failed/{uuid}', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'deleteFailedJob'])->name('api.admin.system.jobs.failed.delete');
            Route::delete('/jobs/failed', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'deleteAllFailedJobs'])->name('api.admin.system.jobs.failed.delete-all');
            
            Route::get('/reports', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'reports'])->name('api.admin.system.reports');
            Route::post('/reports/generate', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'generateReport'])->name('api.admin.system.reports.generate');
            Route::get('/reports/{uuid}/download', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'downloadReport'])->name('api.admin.system.reports.download');
            Route::delete('/reports/{uuid}', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'deleteReport'])->name('api.admin.system.reports.delete');
            
            Route::get('/scheduler', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'scheduler'])->name('api.admin.system.scheduler');
            Route::post('/scheduler/run', [\App\Http\Controllers\Api\V1\Admin\SystemQueueController::class, 'runScheduledTask'])->name('api.admin.system.scheduler.run');
            Route::get('/performance', [\App\Http\Controllers\Api\V1\Admin\PerformanceController::class, 'getStats'])->name('api.admin.system.performance');
        });
    });

    // 6. Notifications routes
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('api.notifications.unread');
        Route::post('/{id}/read', [NotificationController::class, 'read'])->name('api.notifications.read');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
        Route::get('/preferences', [NotificationController::class, 'getPreferences'])->name('api.notifications.get-preferences');
        Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('api.notifications.update-preferences');
    });

    // 7. Analytics routes
    Route::post('/analytics/session', [AnalyticsController::class, 'syncSession'])->name('api.analytics.session');
    Route::post('/analytics/track', [AnalyticsController::class, 'track'])->name('api.analytics.track');
    
    Route::prefix('analytics')->middleware('auth:sanctum')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview'])->name('api.analytics.overview');
        Route::get('/website', [AnalyticsController::class, 'website'])->name('api.analytics.website');
        Route::get('/academy', [AnalyticsController::class, 'academy'])->name('api.analytics.academy');
        Route::get('/events', [AnalyticsController::class, 'events'])->name('api.analytics.events');
        Route::get('/reports', [AnalyticsController::class, 'reports'])->name('api.analytics.reports');
        Route::get('/export', [AnalyticsController::class, 'export'])->name('api.analytics.export');
    });
});
