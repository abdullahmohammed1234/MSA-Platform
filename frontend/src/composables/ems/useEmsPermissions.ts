import { computed } from 'vue';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_PERMISSIONS } from '@/constants/ems';

/**
 * Permission checks for EMS components and navigation.
 *
 * These decide what the interface *offers*. They are not a security boundary:
 * every action they gate is authorized again by a policy on the server, and
 * the API will refuse anything the user is not entitled to regardless of what
 * the UI rendered.
 */
export function useEmsPermissions() {
  const access = useEmsAccessStore();

  const can = (permission: string): boolean => access.can(permission);
  const canAny = (permissions: string[]): boolean => access.canAny(permissions);
  const canAll = (permissions: string[]): boolean => access.canAll(permissions);

  return {
    can,
    canAny,
    canAll,

    permissions: computed(() => access.permissions),
    roles: computed(() => access.roles),
    hasEmsAccess: computed(() => access.hasEmsAccess),

    canViewEvents: computed(() => can(EMS_PERMISSIONS.EVENTS_VIEW)),
    canCreateEvents: computed(() => can(EMS_PERMISSIONS.EVENTS_CREATE)),
    canUpdateEvents: computed(() => can(EMS_PERMISSIONS.EVENTS_UPDATE)),
    canDeleteEvents: computed(() => can(EMS_PERMISSIONS.EVENTS_DELETE)),
    canViewCategories: computed(() => can(EMS_PERMISSIONS.CATEGORIES_VIEW)),
    canManageCategories: computed(() =>
      canAny([
        EMS_PERMISSIONS.CATEGORIES_CREATE,
        EMS_PERMISSIONS.CATEGORIES_UPDATE,
        EMS_PERMISSIONS.CATEGORIES_DELETE,
      ])
    ),
    canViewSystem: computed(() => can(EMS_PERMISSIONS.SYSTEM_VIEW)),

    canViewAttendees: computed(() => can(EMS_PERMISSIONS.REGISTRATIONS_VIEW)),
    canCreateRegistrations: computed(() => can(EMS_PERMISSIONS.REGISTRATIONS_CREATE)),
    canViewCheckIns: computed(() => can(EMS_PERMISSIONS.CHECK_INS_VIEW)),
    canCheckIn: computed(() => can(EMS_PERMISSIONS.CHECK_INS_PERFORM)),
    canUndoCheckIn: computed(() => can(EMS_PERMISSIONS.CHECK_INS_UNDO)),
    canOverrideCheckIn: computed(() => can(EMS_PERMISSIONS.CHECK_INS_OVERRIDE)),
    canImportAttendees: computed(() => can(EMS_PERMISSIONS.IMPORTS_CREATE)),
    canViewImports: computed(() => can(EMS_PERMISSIONS.IMPORTS_VIEW)),
    canViewNotifications: computed(() => can(EMS_PERMISSIONS.NOTIFICATIONS_VIEW)),
    canSendNotifications: computed(() => can(EMS_PERMISSIONS.NOTIFICATIONS_SEND)),
    canManageNotifications: computed(() => can(EMS_PERMISSIONS.NOTIFICATIONS_MANAGE)),
    canViewAnalytics: computed(() => can(EMS_PERMISSIONS.ANALYTICS_VIEW)),
    canManageReports: computed(() => can(EMS_PERMISSIONS.REPORTS_MANAGE)),
    canRefundPayments: computed(() => can(EMS_PERMISSIONS.PAYMENTS_REFUND)),
    canViewOperations: computed(() =>
      canAny([EMS_PERMISSIONS.REGISTRATIONS_VIEW, EMS_PERMISSIONS.CHECK_INS_VIEW])
    ),
    /** Staff-mode heuristic: can check in but cannot edit events. */
    isEventStaffOnly: computed(
      () => can(EMS_PERMISSIONS.CHECK_INS_PERFORM) && !can(EMS_PERMISSIONS.EVENTS_UPDATE)
    ),
  };
}
