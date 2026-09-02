<?php

namespace App\Ems\Support;

/**
 * The canonical EMS permission registry.
 *
 * Permissions are dot-namespaced (`resource.action`) so that new resources can
 * be added without restructuring anything: add the constants, add them to
 * definitions(), and re-run the EMS seeder.
 *
 * These slugs are stored in the platform-wide `permissions` table under the
 * `EMS` module, so the existing `permission:` middleware, the
 * HasRolesAndPermissions trait and the platform RBAC admin screens all keep
 * working without any duplicated authorization plumbing.
 */
final class EmsPermissions
{
    public const MODULE = 'EMS';

    // Events
    public const EVENTS_VIEW = 'events.view';
    public const EVENTS_VIEW_ALL = 'events.view_all';
    public const EVENTS_CREATE = 'events.create';
    public const EVENTS_UPDATE = 'events.update';
    public const EVENTS_DELETE = 'events.delete';
    public const EVENTS_PUBLISH = 'events.publish';
    public const EVENTS_UNPUBLISH = 'events.unpublish';
    public const EVENTS_OPEN_REGISTRATION = 'events.open_registration';
    public const EVENTS_CLOSE_REGISTRATION = 'events.close_registration';
    public const EVENTS_MARK_LIVE = 'events.mark_live';
    public const EVENTS_COMPLETE = 'events.complete';
    public const EVENTS_ARCHIVE = 'events.archive';

    // Event categories
    public const CATEGORIES_VIEW = 'event_categories.view';
    public const CATEGORIES_CREATE = 'event_categories.create';
    public const CATEGORIES_UPDATE = 'event_categories.update';
    public const CATEGORIES_DELETE = 'event_categories.delete';

    // Registrations / attendees (Phase 4)
    public const REGISTRATIONS_VIEW = 'registrations.view';
    public const REGISTRATIONS_CREATE = 'registrations.create';
    public const REGISTRATIONS_UPDATE = 'registrations.update';
    public const REGISTRATIONS_DELETE = 'registrations.delete';

    // Tickets
    public const TICKETS_VIEW = 'tickets.view';
    public const TICKETS_CREATE = 'tickets.create';
    public const TICKETS_UPDATE = 'tickets.update';
    public const TICKETS_DELETE = 'tickets.delete';

    // Check-ins (Phase 4)
    public const CHECK_INS_VIEW = 'check_ins.view';
    public const CHECK_INS_PERFORM = 'check_ins.perform';
    public const CHECK_INS_UNDO = 'check_ins.undo';
    public const CHECK_INS_OVERRIDE = 'check_ins.override';

    // Imports (Phase 4)
    public const IMPORTS_VIEW = 'imports.view';
    public const IMPORTS_CREATE = 'imports.create';

    // Notifications (Phase 5)
    public const NOTIFICATIONS_VIEW = 'notifications.view';
    public const NOTIFICATIONS_SEND = 'notifications.send';
    public const NOTIFICATIONS_MANAGE = 'notifications.manage';
    public const TEMPLATES_VIEW = 'notification_templates.view';
    public const TEMPLATES_MANAGE = 'notification_templates.manage';

    // Lifecycle — cancel (Phase 5)
    public const EVENTS_CANCEL = 'events.cancel';

    // System
    public const SYSTEM_VIEW = 'system.view';
    public const SYSTEM_MANAGE = 'system.manage';

    // Analytics & Reporting (Phase 6)
    public const ANALYTICS_VIEW = 'analytics.view';
    public const ANALYTICS_VIEW_FINANCIAL = 'analytics.view_financial';
    public const REPORTS_MANAGE = 'reports.manage';

    // Advanced Capabilities (Phase 8)
    public const EVENT_TEMPLATES_VIEW = 'templates.view';
    public const EVENT_TEMPLATES_MANAGE = 'templates.manage';
    public const SERIES_VIEW = 'event_series.view';
    public const SERIES_MANAGE = 'event_series.manage';
    public const PROMO_CODES_VIEW = 'promo_codes.view';
    public const PROMO_CODES_MANAGE = 'promo_codes.manage';
    public const FEEDBACK_VIEW = 'feedback.view';
    public const FEEDBACK_SUBMIT = 'feedback.submit';

    public const PAYMENTS_REFUND = 'payments.refund';

    // Volunteering Registrars
    public const VOLUNTEERS_VIEW = 'volunteer.registrations.view';
    public const VOLUNTEERS_UPDATE = 'volunteer.registrations.update';
    public const VOLUNTEERS_DELETE = 'volunteer.registrations.delete';

    /**
     * Every EMS permission with the metadata required by the `permissions`
     * table. `name` is prefixed with "EMS:" because the platform enforces a
     * unique constraint on permission names across all modules.
     *
     * @return array<int, array{slug: string, name: string, group: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            // --- Events -----------------------------------------------------
            [
                'slug' => self::EVENTS_VIEW,
                'name' => 'EMS: View Events',
                'group' => 'Events',
                'description' => 'View events the user is entitled to see.',
            ],
            [
                'slug' => self::EVENTS_VIEW_ALL,
                'name' => 'EMS: View All Events',
                'group' => 'Events',
                'description' => 'Bypass organizer/staff scoping and see every event.',
            ],
            [
                'slug' => self::EVENTS_CREATE,
                'name' => 'EMS: Create Events',
                'group' => 'Events',
                'description' => 'Create new events.',
            ],
            [
                'slug' => self::EVENTS_UPDATE,
                'name' => 'EMS: Update Events',
                'group' => 'Events',
                'description' => 'Edit event details.',
            ],
            [
                'slug' => self::EVENTS_DELETE,
                'name' => 'EMS: Delete Events',
                'group' => 'Events',
                'description' => 'Soft delete events.',
            ],
            [
                'slug' => self::EVENTS_PUBLISH,
                'name' => 'EMS: Publish Events',
                'group' => 'Events',
                'description' => 'Transition an event from draft to published.',
            ],
            [
                'slug' => self::EVENTS_UNPUBLISH,
                'name' => 'EMS: Unpublish Events',
                'group' => 'Events',
                'description' => 'Return a published event to draft.',
            ],
            [
                'slug' => self::EVENTS_OPEN_REGISTRATION,
                'name' => 'EMS: Open Event Registration',
                'group' => 'Events',
                'description' => 'Open registration on a published event.',
            ],
            [
                'slug' => self::EVENTS_CLOSE_REGISTRATION,
                'name' => 'EMS: Close Event Registration',
                'group' => 'Events',
                'description' => 'Close registration on an event.',
            ],
            [
                'slug' => self::EVENTS_MARK_LIVE,
                'name' => 'EMS: Mark Events Live',
                'group' => 'Events',
                'description' => 'Mark an event as currently running.',
            ],
            [
                'slug' => self::EVENTS_COMPLETE,
                'name' => 'EMS: Complete Events',
                'group' => 'Events',
                'description' => 'Mark a live event as completed.',
            ],
            [
                'slug' => self::EVENTS_ARCHIVE,
                'name' => 'EMS: Archive Events',
                'group' => 'Events',
                'description' => 'Archive a completed event.',
            ],
            [
                'slug' => self::EVENTS_CANCEL,
                'name' => 'EMS: Cancel Events',
                'group' => 'Events',
                'description' => 'Cancel a published or live event and notify attendees.',
            ],

            // --- Categories -------------------------------------------------
            [
                'slug' => self::CATEGORIES_VIEW,
                'name' => 'EMS: View Event Categories',
                'group' => 'Event Categories',
                'description' => 'View the event category taxonomy.',
            ],
            [
                'slug' => self::CATEGORIES_CREATE,
                'name' => 'EMS: Create Event Categories',
                'group' => 'Event Categories',
                'description' => 'Create event categories.',
            ],
            [
                'slug' => self::CATEGORIES_UPDATE,
                'name' => 'EMS: Update Event Categories',
                'group' => 'Event Categories',
                'description' => 'Edit and activate/deactivate event categories.',
            ],
            [
                'slug' => self::CATEGORIES_DELETE,
                'name' => 'EMS: Delete Event Categories',
                'group' => 'Event Categories',
                'description' => 'Delete unused event categories.',
            ],

            // --- Registrations ----------------------------------------------
            [
                'slug' => self::REGISTRATIONS_VIEW,
                'name' => 'EMS: View Registrations',
                'group' => 'Registrations',
                'description' => 'View event attendees and registrations.',
            ],
            [
                'slug' => self::REGISTRATIONS_CREATE,
                'name' => 'EMS: Create Registrations',
                'group' => 'Registrations',
                'description' => 'Create walk-in and staff registrations.',
            ],
            [
                'slug' => self::REGISTRATIONS_UPDATE,
                'name' => 'EMS: Update Registrations',
                'group' => 'Registrations',
                'description' => 'Amend attendee registrations.',
            ],
            [
                'slug' => self::REGISTRATIONS_DELETE,
                'name' => 'EMS: Delete Registrations',
                'group' => 'Registrations',
                'description' => 'Cancel or remove registrations.',
            ],

            // --- Tickets ----------------------------------------------------
            [
                'slug' => self::TICKETS_VIEW,
                'name' => 'EMS: View Tickets',
                'group' => 'Tickets',
                'description' => 'View issued tickets and ticket types.',
            ],
            [
                'slug' => self::TICKETS_CREATE,
                'name' => 'EMS: Create Tickets',
                'group' => 'Tickets',
                'description' => 'Issue tickets and define ticket types.',
            ],
            [
                'slug' => self::TICKETS_UPDATE,
                'name' => 'EMS: Update Tickets',
                'group' => 'Tickets',
                'description' => 'Amend tickets and ticket types.',
            ],
            [
                'slug' => self::TICKETS_DELETE,
                'name' => 'EMS: Delete Tickets',
                'group' => 'Tickets',
                'description' => 'Revoke tickets and delete unused ticket types.',
            ],

            // --- Check-ins --------------------------------------------------
            [
                'slug' => self::CHECK_INS_VIEW,
                'name' => 'EMS: View Check-ins',
                'group' => 'Check-ins',
                'description' => 'View attendance and live check-in status.',
            ],
            [
                'slug' => self::CHECK_INS_PERFORM,
                'name' => 'EMS: Perform Check-ins',
                'group' => 'Check-ins',
                'description' => 'Scan tickets and check attendees in.',
            ],
            [
                'slug' => self::CHECK_INS_UNDO,
                'name' => 'EMS: Undo Check-ins',
                'group' => 'Check-ins',
                'description' => 'Undo a previous check-in with an audit reason.',
            ],
            [
                'slug' => self::CHECK_INS_OVERRIDE,
                'name' => 'EMS: Override Check-ins',
                'group' => 'Check-ins',
                'description' => 'Override duplicate check-in protection.',
            ],

            // --- Imports ----------------------------------------------------
            [
                'slug' => self::IMPORTS_VIEW,
                'name' => 'EMS: View Attendee Imports',
                'group' => 'Imports',
                'description' => 'View Excel/CSV import history and previews.',
            ],
            [
                'slug' => self::IMPORTS_CREATE,
                'name' => 'EMS: Import Attendees',
                'group' => 'Imports',
                'description' => 'Upload and import Excel/CSV attendee lists.',
            ],

            // --- Notifications ----------------------------------------------
            [
                'slug' => self::NOTIFICATIONS_VIEW,
                'name' => 'EMS: View Notifications',
                'group' => 'Notifications',
                'description' => 'View notification history and delivery status for events.',
            ],
            [
                'slug' => self::NOTIFICATIONS_SEND,
                'name' => 'EMS: Send Notifications',
                'group' => 'Notifications',
                'description' => 'Resend and manually queue attendee notifications.',
            ],
            [
                'slug' => self::NOTIFICATIONS_MANAGE,
                'name' => 'EMS: Manage Notifications',
                'group' => 'Notifications',
                'description' => 'Configure reminders and notification workflows for events.',
            ],
            [
                'slug' => self::TEMPLATES_VIEW,
                'name' => 'EMS: View Email Templates',
                'group' => 'Notifications',
                'description' => 'View EMS email templates.',
            ],
            [
                'slug' => self::TEMPLATES_MANAGE,
                'name' => 'EMS: Manage Email Templates',
                'group' => 'Notifications',
                'description' => 'Edit EMS email templates.',
            ],

            // --- System -----------------------------------------------------
            [
                'slug' => self::SYSTEM_VIEW,
                'name' => 'EMS: View System Settings',
                'group' => 'System',
                'description' => 'View EMS roles, permissions and configuration.',
            ],
            [
                'slug' => self::SYSTEM_MANAGE,
                'name' => 'EMS: Manage System Settings',
                'group' => 'System',
                'description' => 'Administer EMS-wide configuration, roles and permissions.',
            ],

            // --- Analytics & Reporting --------------------------------------
            [
                'slug' => self::ANALYTICS_VIEW,
                'name' => 'EMS: View Analytics',
                'group' => 'Analytics & Reporting',
                'description' => 'View event attendance, registrations and operational analytics.',
            ],
            [
                'slug' => self::ANALYTICS_VIEW_FINANCIAL,
                'name' => 'EMS: View Financial Analytics',
                'group' => 'Analytics & Reporting',
                'description' => 'View event revenue, refunds and financial analytics.',
            ],
            [
                'slug' => self::REPORTS_MANAGE,
                'name' => 'EMS: Manage Reports',
                'group' => 'Analytics & Reporting',
                'description' => 'Generate and export custom or summary reports.',
            ],
            // --- Advanced Capabilities (Phase 8) ---------------------------
            [
                'slug' => self::EVENT_TEMPLATES_VIEW,
                'name' => 'EMS: View Event Templates',
                'group' => 'Event Templates',
                'description' => 'View reusable event configurations.',
            ],
            [
                'slug' => self::EVENT_TEMPLATES_MANAGE,
                'name' => 'EMS: Manage Event Templates',
                'group' => 'Event Templates',
                'description' => 'Create, edit, and delete event templates.',
            ],
            [
                'slug' => self::SERIES_VIEW,
                'name' => 'EMS: View Event Series',
                'group' => 'Recurring Events',
                'description' => 'View recurring event series and occurrences.',
            ],
            [
                'slug' => self::SERIES_MANAGE,
                'name' => 'EMS: Manage Event Series',
                'group' => 'Recurring Events',
                'description' => 'Create, edit, and cancel recurring event series.',
            ],
            [
                'slug' => self::PROMO_CODES_VIEW,
                'name' => 'EMS: View Promo Codes',
                'group' => 'Promo Codes',
                'description' => 'View promotional codes and usage statistics.',
            ],
            [
                'slug' => self::PROMO_CODES_MANAGE,
                'name' => 'EMS: Manage Promo Codes',
                'group' => 'Promo Codes',
                'description' => 'Create, edit, enable/disable, and archive promo codes.',
            ],
            [
                'slug' => self::FEEDBACK_VIEW,
                'name' => 'EMS: View Feedback',
                'group' => 'Feedback',
                'description' => 'View aggregate ratings and feedback for events.',
            ],
            [
                'slug' => self::FEEDBACK_SUBMIT,
                'name' => 'EMS: Submit Feedback',
                'group' => 'Feedback',
                'description' => 'Submit ratings and feedback for attended events.',
            ],
            [
                'slug' => self::PAYMENTS_REFUND,
                'name' => 'EMS: Refund Payments',
                'group' => 'Payments',
                'description' => 'Issue Square refunds for paid EMS orders.',
            ],
            // --- Volunteering Registrars ------------------------------------
            [
                'slug' => self::VOLUNTEERS_VIEW,
                'name' => 'EMS: View Volunteer Registrations',
                'group' => 'Volunteering Registrars',
                'description' => 'View volunteer submissions and details.',
            ],
            [
                'slug' => self::VOLUNTEERS_UPDATE,
                'name' => 'EMS: Update Volunteer Registrations',
                'group' => 'Volunteering Registrars',
                'description' => 'Update status, notes, and assign administrators to volunteer submissions.',
            ],
            [
                'slug' => self::VOLUNTEERS_DELETE,
                'name' => 'EMS: Delete Volunteer Registrations',
                'group' => 'Volunteering Registrars',
                'description' => 'Archive or soft delete volunteer submissions.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_column(self::definitions(), 'slug');
    }
}
