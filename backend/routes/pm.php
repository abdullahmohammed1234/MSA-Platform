<?php

use App\Pm\Controllers\PmAnalyticsController;
use App\Pm\Controllers\PmAttachmentController;
use App\Pm\Controllers\PmCalendarController;
use App\Pm\Controllers\PmCalendarFeedController;
use App\Pm\Controllers\PmCommentController;
use App\Pm\Controllers\PmDashboardController;
use App\Pm\Controllers\PmDepartmentController;
use App\Pm\Controllers\PmExportController;
use App\Pm\Controllers\PmMemberController;
use App\Pm\Controllers\PmMilestoneController;
use App\Pm\Controllers\PmProjectController;
use App\Pm\Controllers\PmResourceController;
use App\Pm\Controllers\PmSchedulingController;
use App\Pm\Controllers\PmTaskController;
use App\Pm\Controllers\PmTaskDependencyController;
use App\Pm\Controllers\PmTaskListController;
use App\Pm\Controllers\PmWorkloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SFU MSA Project Management System (PMS) Application Routes
|--------------------------------------------------------------------------
| Scoped by auth:sanctum and app.access:projects.
*/

// Dashboard Overview
Route::get('/dashboard', [PmDashboardController::class, 'index'])->name('api.pm.dashboard');

// Analytics Engine (Phase 14)
Route::prefix('analytics')->group(function () {
    Route::get('/dashboard', [PmAnalyticsController::class, 'dashboardAnalytics'])->name('api.pm.analytics.dashboard');
    Route::get('/projects/{uuid}', [PmAnalyticsController::class, 'projectAnalytics'])->name('api.pm.analytics.projects');
    Route::get('/departments/{id}', [PmAnalyticsController::class, 'departmentAnalytics'])->name('api.pm.analytics.departments');
});

// Reports & Export Engine (Phase 14)
Route::get('/projects/{uuid}/export', [PmExportController::class, 'exportProject'])->name('api.pm.projects.export');
Route::get('/tasks/export', [PmExportController::class, 'exportTasks'])->name('api.pm.tasks.export');

// Canonical Departments Roster & Workload
Route::get('/departments', [PmDepartmentController::class, 'index'])->name('api.pm.departments');
Route::get('/departments/{id}/workload', [PmWorkloadController::class, 'departmentWorkload'])->name('api.pm.departments.workload');

// Internal Calendar & Feeds
Route::get('/calendar/events', [PmCalendarController::class, 'events'])->name('api.pm.calendar.events');
Route::get('/calendar/export.ics', [PmCalendarController::class, 'export'])->name('api.pm.calendar.export');
Route::get('/calendar/feeds', [PmCalendarFeedController::class, 'index'])->name('api.pm.calendar.feeds.index');
Route::post('/calendar/feeds', [PmCalendarFeedController::class, 'store'])->name('api.pm.calendar.feeds.store');
Route::delete('/calendar/feeds/{uuid}', [PmCalendarFeedController::class, 'destroy'])->name('api.pm.calendar.feeds.destroy');

// User's own tasks & invitations
Route::get('/tasks/me', [PmTaskController::class, 'myTasks'])->name('api.pm.tasks.me');
Route::get('/invitations/me', [PmMemberController::class, 'myInvitations'])->name('api.pm.invitations.me');
Route::post('/invitations/{memberId}/accept', [PmMemberController::class, 'acceptInvitation'])->name('api.pm.invitations.accept');
Route::post('/invitations/{memberId}/decline', [PmMemberController::class, 'declineInvitation'])->name('api.pm.invitations.decline');

// Bulk Tasks Operations
Route::post('/tasks/bulk', [PmTaskController::class, 'bulk'])->name('api.pm.tasks.bulk');

// Projects CRUD & Actions
Route::prefix('projects')->group(function () {
    Route::get('/', [PmProjectController::class, 'index'])->name('api.pm.projects.index');
    Route::post('/', [PmProjectController::class, 'store'])->name('api.pm.projects.store');
    Route::get('/{uuid}', [PmProjectController::class, 'show'])->name('api.pm.projects.show');
    Route::put('/{uuid}', [PmProjectController::class, 'update'])->name('api.pm.projects.update');
    Route::delete('/{uuid}', [PmProjectController::class, 'destroy'])->name('api.pm.projects.destroy');
    Route::post('/{uuid}/archive', [PmProjectController::class, 'archive'])->name('api.pm.projects.archive');
    Route::post('/{uuid}/restore', [PmProjectController::class, 'restore'])->name('api.pm.projects.restore');

    // Eligible Task Assignees
    Route::get('/{uuid}/eligible-assignees', [PmTaskController::class, 'eligibleAssignees'])->name('api.pm.projects.eligible-assignees');

    // Task Lists / Custom Sections
    Route::get('/{uuid}/lists', [PmTaskListController::class, 'index'])->name('api.pm.projects.lists.index');
    Route::post('/{uuid}/lists', [PmTaskListController::class, 'store'])->name('api.pm.projects.lists.store');
    Route::post('/{uuid}/lists/reorder', [PmTaskListController::class, 'reorder'])->name('api.pm.projects.lists.reorder');

    // Project Attachments
    Route::get('/{uuid}/attachments', [PmAttachmentController::class, 'indexProject'])->name('api.pm.projects.attachments.index');
    Route::post('/{uuid}/attachments', [PmAttachmentController::class, 'storeProject'])->name('api.pm.projects.attachments.store');

    // Project Members & Collaboration Invitations
    Route::get('/{uuid}/members', [PmMemberController::class, 'index'])->name('api.pm.projects.members.index');
    Route::post('/{uuid}/members/invite', [PmMemberController::class, 'invite'])->name('api.pm.projects.members.invite');
    Route::put('/{uuid}/members/{memberId}/role', [PmMemberController::class, 'updateMemberRole'])->name('api.pm.projects.members.update-role');
    Route::patch('/{uuid}/members/{memberId}/role', [PmMemberController::class, 'updateMemberRole']);
    Route::delete('/{uuid}/members/{memberId}', [PmMemberController::class, 'removeMember'])->name('api.pm.projects.members.remove');

    // Milestones
    Route::get('/{uuid}/milestones', [PmMilestoneController::class, 'index'])->name('api.pm.projects.milestones.index');
    Route::post('/{uuid}/milestones', [PmMilestoneController::class, 'store'])->name('api.pm.projects.milestones.store');

    // Tasks under Project
    Route::get('/{uuid}/tasks', [PmTaskController::class, 'index'])->name('api.pm.projects.tasks.index');
    Route::post('/{uuid}/tasks', [PmTaskController::class, 'store'])->name('api.pm.projects.tasks.store');

    // Comments under Project
    Route::get('/{uuid}/comments', [PmCommentController::class, 'index'])->name('api.pm.projects.comments.index');
    Route::post('/{uuid}/comments', [PmCommentController::class, 'store'])->name('api.pm.projects.comments.store');
    Route::delete('/{uuid}/comments/{id}', [PmCommentController::class, 'destroy'])->name('api.pm.projects.comments.destroy');

    // Workload & Resource Balancing (Phase 13)
    Route::get('/{uuid}/workload', [PmWorkloadController::class, 'projectWorkload'])->name('api.pm.projects.workload');
    Route::get('/{uuid}/balancing-suggestions', [PmResourceController::class, 'balancingSuggestions'])->name('api.pm.projects.balancing-suggestions');
    Route::post('/{uuid}/balancing-suggestions/apply', [PmResourceController::class, 'applyBalancing'])->name('api.pm.projects.balancing-suggestions.apply');

    // Native SVG Gantt Chart Engine (Phase 13)
    Route::get('/{uuid}/gantt', [PmSchedulingController::class, 'projectGantt'])->name('api.pm.projects.gantt');
});

// Resource Capacity Management (Phase 13)
Route::prefix('resources')->group(function () {
    Route::get('/capacities', [PmResourceController::class, 'indexCapacities'])->name('api.pm.resources.capacities.index');
    Route::get('/capacities/{userId}', [PmResourceController::class, 'showCapacity'])->name('api.pm.resources.capacities.show');
    Route::put('/capacities/{userId}', [PmResourceController::class, 'updateCapacity'])->name('api.pm.resources.capacities.update');
});

// Standalone Task Lists Actions (by list UUID)
Route::prefix('lists')->group(function () {
    Route::put('/{uuid}', [PmTaskListController::class, 'update'])->name('api.pm.lists.update');
    Route::delete('/{uuid}', [PmTaskListController::class, 'destroy'])->name('api.pm.lists.destroy');
});

// Standalone Milestones Actions (by milestone UUID)
Route::prefix('milestones')->group(function () {
    Route::put('/{uuid}', [PmMilestoneController::class, 'update'])->name('api.pm.milestones.update');
    Route::delete('/{uuid}', [PmMilestoneController::class, 'destroy'])->name('api.pm.milestones.destroy');
    Route::get('/{uuid}/condition', [PmSchedulingController::class, 'milestoneCondition'])->name('api.pm.milestones.condition');
});

// Standalone Tasks Actions (by task UUID)
Route::prefix('tasks')->group(function () {
    Route::get('/{uuid}', [PmTaskController::class, 'show'])->name('api.pm.tasks.show');
    Route::put('/{uuid}', [PmTaskController::class, 'update'])->name('api.pm.tasks.update');
    Route::delete('/{uuid}', [PmTaskController::class, 'destroy'])->name('api.pm.tasks.destroy');
    Route::patch('/{uuid}/status', [PmTaskController::class, 'updateStatus'])->name('api.pm.tasks.update-status');

    // Task Scheduling & Rescheduling Engine (Phase 12)
    Route::get('/{uuid}/scheduling', [PmSchedulingController::class, 'scheduling'])->name('api.pm.tasks.scheduling');
    Route::post('/{uuid}/reschedule/preview', [PmSchedulingController::class, 'previewReschedule'])->name('api.pm.tasks.reschedule.preview');
    Route::post('/{uuid}/reschedule', [PmSchedulingController::class, 'reschedule'])->name('api.pm.tasks.reschedule');

    // Task Dependencies
    Route::get('/{uuid}/dependencies', [PmTaskDependencyController::class, 'index'])->name('api.pm.tasks.dependencies.index');
    Route::post('/{uuid}/dependencies', [PmTaskDependencyController::class, 'store'])->name('api.pm.tasks.dependencies.store');
    Route::delete('/{uuid}/dependencies/{dependsOnUuid}', [PmTaskDependencyController::class, 'destroy'])->name('api.pm.tasks.dependencies.destroy');

    // Task Attachments
    Route::get('/{uuid}/attachments', [PmAttachmentController::class, 'indexTask'])->name('api.pm.tasks.attachments.index');
    Route::post('/{uuid}/attachments', [PmAttachmentController::class, 'storeTask'])->name('api.pm.tasks.attachments.store');
});

// Standalone Attachments Actions (by attachment UUID)
Route::prefix('attachments')->group(function () {
    Route::get('/{uuid}/download', [PmAttachmentController::class, 'download'])->name('api.pm.attachments.download');
    Route::delete('/{uuid}', [PmAttachmentController::class, 'destroy'])->name('api.pm.attachments.destroy');
    Route::post('/{uuid}/restore', [PmAttachmentController::class, 'restore'])->name('api.pm.attachments.restore');
    Route::delete('/{uuid}/force', [PmAttachmentController::class, 'forceDestroy'])->name('api.pm.attachments.force-destroy');
    Route::post('/cleanup', [PmAttachmentController::class, 'reconcileCleanup'])->name('api.pm.attachments.cleanup');
});
