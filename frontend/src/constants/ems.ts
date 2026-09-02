/**
 * EMS permission slugs, mirroring App\Ems\Support\EmsPermissions.
 *
 * Kept as constants so navigation, route meta and components refer to the
 * same strings. These drive presentation only — every one of them is enforced
 * again by a policy on the server.
 */
export const EMS_PERMISSIONS = {
  EVENTS_VIEW: 'events.view',
  EVENTS_VIEW_ALL: 'events.view_all',
  EVENTS_CREATE: 'events.create',
  EVENTS_UPDATE: 'events.update',
  EVENTS_DELETE: 'events.delete',
  EVENTS_PUBLISH: 'events.publish',
  EVENTS_UNPUBLISH: 'events.unpublish',
  EVENTS_OPEN_REGISTRATION: 'events.open_registration',
  EVENTS_CLOSE_REGISTRATION: 'events.close_registration',
  EVENTS_MARK_LIVE: 'events.mark_live',
  EVENTS_COMPLETE: 'events.complete',
  EVENTS_ARCHIVE: 'events.archive',

  CATEGORIES_VIEW: 'event_categories.view',
  CATEGORIES_CREATE: 'event_categories.create',
  CATEGORIES_UPDATE: 'event_categories.update',
  CATEGORIES_DELETE: 'event_categories.delete',

  REGISTRATIONS_VIEW: 'registrations.view',
  REGISTRATIONS_CREATE: 'registrations.create',
  REGISTRATIONS_UPDATE: 'registrations.update',
  REGISTRATIONS_DELETE: 'registrations.delete',

  TICKETS_VIEW: 'tickets.view',
  TICKETS_CREATE: 'tickets.create',
  TICKETS_UPDATE: 'tickets.update',
  TICKETS_DELETE: 'tickets.delete',

  CHECK_INS_VIEW: 'check_ins.view',
  CHECK_INS_PERFORM: 'check_ins.perform',
  CHECK_INS_UNDO: 'check_ins.undo',
  CHECK_INS_OVERRIDE: 'check_ins.override',

  IMPORTS_VIEW: 'imports.view',
  IMPORTS_CREATE: 'imports.create',

  NOTIFICATIONS_VIEW: 'notifications.view',
  NOTIFICATIONS_SEND: 'notifications.send',
  NOTIFICATIONS_MANAGE: 'notifications.manage',
  TEMPLATES_VIEW: 'notification_templates.view',
  TEMPLATES_MANAGE: 'notification_templates.manage',

  EVENTS_CANCEL: 'events.cancel',

  SYSTEM_VIEW: 'system.view',
  SYSTEM_MANAGE: 'system.manage',

  ANALYTICS_VIEW: 'analytics.view',
  ANALYTICS_VIEW_FINANCIAL: 'analytics.view_financial',
  REPORTS_MANAGE: 'reports.manage',

  PAYMENTS_REFUND: 'payments.refund',

  VOLUNTEERS_VIEW: 'volunteer.registrations.view',
  VOLUNTEERS_UPDATE: 'volunteer.registrations.update',
  VOLUNTEERS_DELETE: 'volunteer.registrations.delete',
} as const;

export type EmsPermissionSlug = (typeof EMS_PERMISSIONS)[keyof typeof EMS_PERMISSIONS];

/** Where the EMS shell is mounted in the platform's router. */
export const EMS_BASE_PATH = '/ems';

/** Public discovery & registration (Phase 2) — outside the admin shell. */
export const EMS_PUBLIC_EVENTS_PATH = '/events';
export const EMS_PUBLIC_CALENDAR_PATH = '/events/calendar';
export const EMS_PUBLIC_TICKET_PATH = '/tickets';

/** Absolute path helpers for public event deep links. */
export function emsPublicEventPath(slug: string): string {
  return `${EMS_PUBLIC_EVENTS_PATH}/${slug}`;
}

export function emsPublicCheckoutSuccessPath(slug: string): string {
  return `${emsPublicEventPath(slug)}/checkout/success`;
}

export function emsPublicCheckoutCancelPath(slug: string): string {
  return `${emsPublicEventPath(slug)}/checkout/cancel`;
}
