<?php

namespace App\Ems\Support;

/**
 * The EMS role registry.
 *
 * Roles live in the platform-wide `roles` table. `super-admin` already exists
 * on the platform and is reused as the EMS Super Admin rather than creating a
 * parallel one; the remaining four roles are EMS-specific.
 */
final class EmsRoles
{
    public const SUPER_ADMIN = 'super-admin';
    public const EVENT_ADMINISTRATOR = 'event-administrator';
    public const EVENT_ORGANIZER = 'event-organizer';
    public const EVENT_STAFF = 'event-staff';
    public const ATTENDEE = 'attendee';

    /**
     * Roles owned and seeded by the EMS module. `super-admin` is deliberately
     * excluded: it is a platform role that the EMS grants permissions to but
     * does not own.
     *
     * @return array<int, array{slug: string, name: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => self::EVENT_ADMINISTRATOR,
                'name' => 'Event Administrator',
                'description' => 'Runs the event programme: events, categories, organizers, staff and lifecycle. No system-level administration.',
            ],
            [
                'slug' => self::EVENT_ORGANIZER,
                'name' => 'Event Organizer',
                'description' => 'Creates and runs their own events, including the lifecycle transitions they are authorized for.',
            ],
            [
                'slug' => self::EVENT_STAFF,
                'name' => 'Event Staff',
                'description' => 'Checks in attendees for assigned events. No revenue, settings, or ticket configuration access.',
            ],
            [
                'slug' => self::ATTENDEE,
                'name' => 'Attendee',
                'description' => 'Community member who attends events. Registration and ticketing arrive in Phase 2.',
            ],
        ];
    }

    /**
     * Permission grants per role.
     *
     * Authorization is always evaluated against these granular permissions —
     * never against role names — so capabilities can be re-cut without
     * touching any policy, controller or component.
     *
     * @return array<string, array<int, string>>
     */
    public static function permissionMatrix(): array
    {
        return [
            // Full EMS access, including system administration.
            self::SUPER_ADMIN => EmsPermissions::all(),

            // Full event programme control, explicitly WITHOUT system.manage.
            self::EVENT_ADMINISTRATOR => [
                EmsPermissions::EVENTS_VIEW,
                EmsPermissions::EVENTS_VIEW_ALL,
                EmsPermissions::EVENTS_CREATE,
                EmsPermissions::EVENTS_UPDATE,
                EmsPermissions::EVENTS_DELETE,
                EmsPermissions::EVENTS_PUBLISH,
                EmsPermissions::EVENTS_UNPUBLISH,
                EmsPermissions::EVENTS_OPEN_REGISTRATION,
                EmsPermissions::EVENTS_CLOSE_REGISTRATION,
                EmsPermissions::EVENTS_MARK_LIVE,
                EmsPermissions::EVENTS_COMPLETE,
                EmsPermissions::EVENTS_ARCHIVE,
                EmsPermissions::EVENTS_CANCEL,
                EmsPermissions::CATEGORIES_VIEW,
                EmsPermissions::CATEGORIES_CREATE,
                EmsPermissions::CATEGORIES_UPDATE,
                EmsPermissions::CATEGORIES_DELETE,
                EmsPermissions::REGISTRATIONS_VIEW,
                EmsPermissions::REGISTRATIONS_CREATE,
                EmsPermissions::REGISTRATIONS_UPDATE,
                EmsPermissions::REGISTRATIONS_DELETE,
                EmsPermissions::TICKETS_VIEW,
                EmsPermissions::TICKETS_CREATE,
                EmsPermissions::TICKETS_UPDATE,
                EmsPermissions::TICKETS_DELETE,
                EmsPermissions::CHECK_INS_VIEW,
                EmsPermissions::CHECK_INS_PERFORM,
                EmsPermissions::CHECK_INS_UNDO,
                EmsPermissions::CHECK_INS_OVERRIDE,
                EmsPermissions::IMPORTS_VIEW,
                EmsPermissions::IMPORTS_CREATE,
                EmsPermissions::NOTIFICATIONS_VIEW,
                EmsPermissions::NOTIFICATIONS_SEND,
                EmsPermissions::NOTIFICATIONS_MANAGE,
                EmsPermissions::TEMPLATES_VIEW,
                EmsPermissions::TEMPLATES_MANAGE,
                EmsPermissions::SYSTEM_VIEW,
                EmsPermissions::ANALYTICS_VIEW,
                EmsPermissions::ANALYTICS_VIEW_FINANCIAL,
                EmsPermissions::REPORTS_MANAGE,
                EmsPermissions::EVENT_TEMPLATES_VIEW,
                EmsPermissions::EVENT_TEMPLATES_MANAGE,
                EmsPermissions::SERIES_VIEW,
                EmsPermissions::SERIES_MANAGE,
                EmsPermissions::PROMO_CODES_VIEW,
                EmsPermissions::PROMO_CODES_MANAGE,
                EmsPermissions::FEEDBACK_VIEW,
                EmsPermissions::FEEDBACK_SUBMIT,
                EmsPermissions::PAYMENTS_REFUND,
                EmsPermissions::VOLUNTEERS_VIEW,
                EmsPermissions::VOLUNTEERS_UPDATE,
                EmsPermissions::VOLUNTEERS_DELETE,
            ],

            // Scoped to the events they organize: no events.view_all grant.
            self::EVENT_ORGANIZER => [
                EmsPermissions::EVENTS_VIEW,
                EmsPermissions::EVENTS_CREATE,
                EmsPermissions::EVENTS_UPDATE,
                EmsPermissions::EVENTS_PUBLISH,
                EmsPermissions::EVENTS_UNPUBLISH,
                EmsPermissions::EVENTS_OPEN_REGISTRATION,
                EmsPermissions::EVENTS_CLOSE_REGISTRATION,
                EmsPermissions::EVENTS_MARK_LIVE,
                EmsPermissions::EVENTS_COMPLETE,
                EmsPermissions::EVENTS_CANCEL,
                EmsPermissions::CATEGORIES_VIEW,
                EmsPermissions::REGISTRATIONS_VIEW,
                EmsPermissions::REGISTRATIONS_CREATE,
                EmsPermissions::REGISTRATIONS_UPDATE,
                EmsPermissions::REGISTRATIONS_DELETE,
                EmsPermissions::TICKETS_VIEW,
                EmsPermissions::TICKETS_CREATE,
                EmsPermissions::TICKETS_UPDATE,
                EmsPermissions::TICKETS_DELETE,
                EmsPermissions::CHECK_INS_VIEW,
                EmsPermissions::CHECK_INS_PERFORM,
                EmsPermissions::CHECK_INS_UNDO,
                EmsPermissions::CHECK_INS_OVERRIDE,
                EmsPermissions::IMPORTS_VIEW,
                EmsPermissions::IMPORTS_CREATE,
                EmsPermissions::NOTIFICATIONS_VIEW,
                EmsPermissions::NOTIFICATIONS_SEND,
                EmsPermissions::NOTIFICATIONS_MANAGE,
                EmsPermissions::TEMPLATES_VIEW,
                EmsPermissions::ANALYTICS_VIEW,
                EmsPermissions::ANALYTICS_VIEW_FINANCIAL,
                EmsPermissions::REPORTS_MANAGE,
                EmsPermissions::EVENT_TEMPLATES_VIEW,
                EmsPermissions::SERIES_VIEW,
                EmsPermissions::SERIES_MANAGE,
                EmsPermissions::PROMO_CODES_VIEW,
                EmsPermissions::PROMO_CODES_MANAGE,
                EmsPermissions::FEEDBACK_VIEW,
                EmsPermissions::FEEDBACK_SUBMIT,
                EmsPermissions::PAYMENTS_REFUND,
            ],

            // Event-day operations only: check-in, search, walk-ins.
            self::EVENT_STAFF => [
                EmsPermissions::EVENTS_VIEW,
                EmsPermissions::CATEGORIES_VIEW,
                EmsPermissions::REGISTRATIONS_VIEW,
                EmsPermissions::REGISTRATIONS_CREATE,
                EmsPermissions::CHECK_INS_VIEW,
                EmsPermissions::CHECK_INS_PERFORM,
            ],

            // Profile only in Phase 1. Public event discovery arrives in Phase 2.
            self::ATTENDEE => [],
        ];
    }
}
