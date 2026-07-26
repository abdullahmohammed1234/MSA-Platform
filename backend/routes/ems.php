<?php

use App\Ems\Http\Controllers\V1\AccessController;
use App\Ems\Http\Controllers\V1\AnalyticsController;
use App\Ems\Http\Controllers\V1\DashboardController;
use App\Ems\Http\Controllers\V1\EmailTemplateController;
use App\Ems\Http\Controllers\V1\EventCategoryController;
use App\Ems\Http\Controllers\V1\EventController;
use App\Ems\Http\Controllers\V1\EventLifecycleController;
use App\Ems\Http\Controllers\V1\EventNotificationController;
use App\Ems\Http\Controllers\V1\EventOperationsController;
use App\Ems\Http\Controllers\V1\EventReminderController;
use App\Ems\Http\Controllers\V1\NotificationPreferenceController;
use App\Ems\Http\Controllers\V1\OrderController;
use App\Ems\Http\Controllers\V1\PaymentController;
use App\Ems\Http\Controllers\V1\Public\PublicEventController;
use App\Ems\Http\Controllers\V1\RegistrationController;
use App\Ems\Http\Controllers\V1\TicketTypeController;
use App\Ems\Http\Controllers\V1\EventTemplateController;
use App\Ems\Http\Controllers\V1\EventSeriesController;
use App\Ems\Http\Controllers\V1\PromoCodeController;
use App\Ems\Http\Controllers\V1\FeedbackController;
use App\Ems\Http\Controllers\V1\CalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MSA Event Management System API — v1
|--------------------------------------------------------------------------
|
| Registered by App\Ems\EmsServiceProvider under the prefix configured at
| ems.route.prefix, which defaults to /api/v1/ems.
|
| Public discovery / registration / tickets live under /public and do not
| require authentication. Operational EMS routes require a Sanctum bearer
| token from the platform's POST /api/v1/auth/login.
|
| Authorization on authenticated routes is enforced by policies inside the
| controllers, not by route middleware.
|
| Square webhooks are registered separately at /api/v1/webhooks/square.
|
*/

// --- Public surface (Phase 2 / 3) ---------------------------------------
Route::prefix('public')
    ->middleware(['throttle:' . config('ems.public.throttle', 'ems_public')])
    ->name('public.')
    ->group(function (): void {
        Route::get('/events', [PublicEventController::class, 'index'])
            ->name('events.index');
        Route::get('/events/calendar', [PublicEventController::class, 'calendar'])
            ->name('events.calendar');
        Route::get('/events/{slug}', [PublicEventController::class, 'show'])
            ->name('events.show');
        Route::get('/events/{slug}/calendar', [CalendarController::class, 'links'])
            ->name('events.calendar-links');
        Route::get('/events/{slug}/ics', [CalendarController::class, 'ics'])
            ->name('events.ics');
        Route::get('/events/{slug}/tickets', [PublicEventController::class, 'tickets'])
            ->name('events.tickets');
        Route::post('/events/{slug}/register', [PublicEventController::class, 'register'])
            ->middleware('throttle:' . config('ems.public.registration_throttle', 'ems_registration'))
            ->name('events.register');
        Route::post('/events/{slug}/checkout', [PublicEventController::class, 'checkout'])
            ->middleware('throttle:' . config('ems.public.registration_throttle', 'ems_registration'))
            ->name('events.checkout');
        Route::post('/events/{slug}/waitlist', [PublicEventController::class, 'joinWaitlist'])
            ->middleware('throttle:' . config('ems.public.registration_throttle', 'ems_registration'))
            ->name('events.waitlist.join');
        Route::delete('/events/{slug}/waitlist/{entry}', [PublicEventController::class, 'leaveWaitlist'])
            ->middleware('throttle:' . config('ems.public.registration_throttle', 'ems_registration'))
            ->name('events.waitlist.leave');

        Route::get('/categories', [PublicEventController::class, 'categories'])
            ->name('categories.index');

        Route::post('/promo-codes/validate', [PromoCodeController::class, 'validateCode'])
            ->name('promo-codes.validate');

        // validate must be declared before the {code} catch-all.
        Route::get('/tickets/validate/{code}', [PublicEventController::class, 'validateTicket'])
            ->name('tickets.validate');
        Route::get('/tickets/{code}', [PublicEventController::class, 'showTicket'])
            ->name('tickets.show');
        Route::get('/tickets/{code}/qr', [PublicEventController::class, 'ticketQr'])
            ->name('tickets.qr');
    });

// --- Authenticated operations (Phase 1 / 3) -----------------------------
Route::middleware(['auth:sanctum', 'throttle:' . config('ems.route.throttle', 'ems_api')])
    ->group(function (): void {

        // --- Identity & access model -----------------------------------
        Route::get('/users/me', [AccessController::class, 'me'])->name('me');
        Route::get('/roles', [AccessController::class, 'roles'])->name('roles.index');
        Route::get('/permissions', [AccessController::class, 'permissions'])->name('permissions.index');

        // --- Dashboard --------------------------------------------------
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // --- Events -----------------------------------------------------
        Route::get('/events/lifecycle', [EventLifecycleController::class, 'describe'])
            ->name('events.lifecycle');

        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::patch('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        Route::post('/events/{event}/transitions', [EventLifecycleController::class, 'store'])
            ->name('events.transitions.store');

        // --- Ticket types (Phase 3) -------------------------------------
        Route::get('/events/{event}/tickets', [TicketTypeController::class, 'index'])
            ->name('events.tickets.index');
        Route::post('/events/{event}/tickets', [TicketTypeController::class, 'store'])
            ->name('events.tickets.store');
        Route::post('/events/{event}/tickets/reorder', [TicketTypeController::class, 'reorder'])
            ->name('events.tickets.reorder');
        Route::get('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'show'])
            ->name('events.tickets.show');
        Route::put('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'update'])
            ->name('events.tickets.update');
        Route::patch('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'update']);
        Route::delete('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'destroy'])
            ->name('events.tickets.destroy');
        Route::post('/events/{event}/tickets/{ticketType}/disable', [TicketTypeController::class, 'disable'])
            ->name('events.tickets.disable');
        Route::post('/events/{event}/tickets/{ticketType}/duplicate', [TicketTypeController::class, 'duplicate'])
            ->name('events.tickets.duplicate');
        Route::get('/events/{event}/payment-summary', [TicketTypeController::class, 'paymentSummary'])
            ->name('events.payment-summary');

        // --- Phase 4: Operations, attendees, import, check-in --------------
        Route::get('/events/{event}/operations', [EventOperationsController::class, 'operations'])
            ->name('events.operations');
        Route::get('/events/{event}/attendees', [EventOperationsController::class, 'attendees'])
            ->name('events.attendees');
        Route::get('/events/{event}/check-ins/recent', [EventOperationsController::class, 'recentCheckIns'])
            ->name('events.check-ins.recent');

        Route::post('/events/{event}/validate-ticket', [EventOperationsController::class, 'validateTicket'])
            ->middleware('throttle:' . config('ems.operations.check_in_throttle', 'ems_check_in'))
            ->name('events.validate-ticket');
        Route::post('/events/{event}/check-in', [EventOperationsController::class, 'checkIn'])
            ->middleware('throttle:' . config('ems.operations.check_in_throttle', 'ems_check_in'))
            ->name('events.check-in');
        Route::post('/events/{event}/manual-check-in', [EventOperationsController::class, 'manualCheckIn'])
            ->middleware('throttle:' . config('ems.operations.check_in_throttle', 'ems_check_in'))
            ->name('events.manual-check-in');
        Route::post('/events/{event}/walk-in', [EventOperationsController::class, 'walkIn'])
            ->name('events.walk-in');
        Route::post('/events/{event}/undo-check-in', [EventOperationsController::class, 'undoCheckIn'])
            ->name('events.undo-check-in');

        Route::post('/events/{event}/import/preview', [EventOperationsController::class, 'previewImport'])
            ->name('events.import.preview');
        Route::post('/events/{event}/import', [EventOperationsController::class, 'commitImport'])
            ->name('events.import.commit');
        Route::get('/events/{event}/import/mappings', [EventOperationsController::class, 'listMappings'])
            ->name('events.import.mappings.index');
        Route::post('/events/{event}/import/mappings', [EventOperationsController::class, 'saveMapping'])
            ->name('events.import.mappings.store');

        // --- Phase 5: Communications ------------------------------------
        Route::get('/events/{event}/notifications/summary', [EventNotificationController::class, 'summary'])
            ->name('events.notifications.summary');
        Route::get('/events/{event}/notifications', [EventNotificationController::class, 'index'])
            ->name('events.notifications.index');
        Route::post('/events/{event}/notifications/resend', [EventNotificationController::class, 'resend'])
            ->name('events.notifications.resend');
        Route::post('/events/{event}/notifications/{notification}/retry', [EventNotificationController::class, 'retry'])
            ->name('events.notifications.retry');

        Route::get('/events/{event}/reminders', [EventReminderController::class, 'index'])
            ->name('events.reminders.index');
        Route::post('/events/{event}/reminders', [EventReminderController::class, 'store'])
            ->name('events.reminders.store');
        Route::put('/events/{event}/reminders/{reminder}', [EventReminderController::class, 'update'])
            ->name('events.reminders.update');
        Route::patch('/events/{event}/reminders/{reminder}', [EventReminderController::class, 'update']);
        Route::delete('/events/{event}/reminders/{reminder}', [EventReminderController::class, 'destroy'])
            ->name('events.reminders.destroy');

        Route::get('/notifications/{notification}', [EventNotificationController::class, 'show'])
            ->name('notifications.show');

        Route::get('/email-templates', [EmailTemplateController::class, 'index'])
            ->name('email-templates.index');
        Route::get('/email-templates/{template}', [EmailTemplateController::class, 'show'])
            ->name('email-templates.show');
        Route::put('/email-templates/{template}', [EmailTemplateController::class, 'update'])
            ->name('email-templates.update');
        Route::patch('/email-templates/{template}', [EmailTemplateController::class, 'update']);

        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show'])
            ->name('notification-preferences.show');
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])
            ->name('notification-preferences.update');
        Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update']);

        // --- Orders / payments / registrations --------------------------
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/registrations/{registration}', [RegistrationController::class, 'show'])
            ->name('registrations.show');

        // --- Event categories -------------------------------------------
        Route::get('/event-categories', [EventCategoryController::class, 'index'])
            ->name('event-categories.index');
        Route::post('/event-categories', [EventCategoryController::class, 'store'])
            ->name('event-categories.store');
        Route::get('/event-categories/{category}', [EventCategoryController::class, 'show'])
            ->name('event-categories.show');
        Route::put('/event-categories/{category}', [EventCategoryController::class, 'update'])
            ->name('event-categories.update');
        Route::patch('/event-categories/{category}', [EventCategoryController::class, 'update']);
        Route::delete('/event-categories/{category}', [EventCategoryController::class, 'destroy'])
            ->name('event-categories.destroy');

        // --- Event templates (Phase 8) ----------------------------------
        Route::get('/event-templates', [EventTemplateController::class, 'index'])
            ->name('event-templates.index');
        Route::post('/event-templates', [EventTemplateController::class, 'store'])
            ->name('event-templates.store');
        Route::get('/event-templates/{template}', [EventTemplateController::class, 'show'])
            ->name('event-templates.show');
        Route::put('/event-templates/{template}', [EventTemplateController::class, 'update'])
            ->name('event-templates.update');
        Route::patch('/event-templates/{template}', [EventTemplateController::class, 'update']);
        Route::delete('/event-templates/{template}', [EventTemplateController::class, 'destroy'])
            ->name('event-templates.destroy');
        Route::post('/event-templates/{template}/duplicate', [EventTemplateController::class, 'duplicate'])
            ->name('event-templates.duplicate');
        Route::post('/event-templates/{template}/default', [EventTemplateController::class, 'setDefault'])
            ->name('event-templates.default');

        // --- Event series (Phase 8) -------------------------------------
        Route::get('/event-series', [EventSeriesController::class, 'index'])
            ->name('event-series.index');
        Route::post('/event-series', [EventSeriesController::class, 'store'])
            ->name('event-series.store');
        Route::get('/event-series/{series}', [EventSeriesController::class, 'show'])
            ->name('event-series.show');
        Route::put('/event-series/{series}', [EventSeriesController::class, 'update'])
            ->name('event-series.update');
        Route::put('/event-series/{series}/events/{event}', [EventSeriesController::class, 'updateFuture'])
            ->name('event-series.events.update-future');
        Route::delete('/event-series/{series}', [EventSeriesController::class, 'destroy'])
            ->name('event-series.destroy');

        // --- Promo codes (Phase 8) --------------------------------------
        Route::get('/promo-codes', [PromoCodeController::class, 'index'])
            ->name('promo-codes.index');
        Route::post('/promo-codes', [PromoCodeController::class, 'store'])
            ->name('promo-codes.store');
        Route::get('/promo-codes/{promoCode}', [PromoCodeController::class, 'show'])
            ->name('promo-codes.show');
        Route::put('/promo-codes/{promoCode}', [PromoCodeController::class, 'update'])
            ->name('promo-codes.update');
        Route::delete('/promo-codes/{promoCode}', [PromoCodeController::class, 'destroy'])
            ->name('promo-codes.destroy');
        Route::post('/promo-codes/validate', [PromoCodeController::class, 'validateCode'])
            ->name('promo-codes.validate-authenticated');

        // --- Phase 6: Analytics & Reports -------------------------------
        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])
            ->name('analytics.dashboard');
        Route::get('/analytics/advanced-report', [AnalyticsController::class, 'advancedReport'])
            ->name('analytics.advanced-report');
        Route::get('/analytics/compare', [AnalyticsController::class, 'compare'])
            ->name('analytics.compare');
        Route::get('/reports/{report}/download', [AnalyticsController::class, 'download'])
            ->name('reports.download');

        Route::get('/events/{event}/analytics', [AnalyticsController::class, 'analytics'])
            ->name('events.analytics');
        Route::get('/events/{event}/attendance', [AnalyticsController::class, 'attendance'])
            ->name('events.attendance');
        Route::get('/events/{event}/revenue', [AnalyticsController::class, 'revenue'])
            ->name('events.revenue');
        Route::get('/events/{event}/reports', [AnalyticsController::class, 'reports'])
            ->name('events.reports');
        Route::post('/events/{event}/reports/export', [AnalyticsController::class, 'export'])
            ->name('events.reports.export');

        // --- Event feedback (Phase 8) -----------------------------------
        Route::get('/events/{event}/feedback', [FeedbackController::class, 'index'])
            ->name('events.feedback.index');
        Route::post('/events/{event}/feedback', [FeedbackController::class, 'store'])
            ->name('events.feedback.store');
    });
