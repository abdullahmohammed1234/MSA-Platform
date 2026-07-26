import { emsHttp } from './emsClient';
import type { EmsCurrentUser, EmsDashboard, EmsPermission, EmsRole } from '@/types/ems';

/**
 * Identity and access endpoints.
 *
 * There is no login or logout here: the EMS authenticates against the
 * platform's existing Sanctum endpoints through the shared auth store, and
 * only asks the EMS API "who am I inside this module".
 */
export const accessService = {
  /** GET /ems/users/me — the viewer with their EMS permissions resolved. */
  me(): Promise<EmsCurrentUser> {
    return emsHttp.get<EmsCurrentUser>('/users/me');
  },

  /** GET /ems/roles — requires system.view. */
  roles(): Promise<EmsRole[]> {
    return emsHttp.get<EmsRole[]>('/roles');
  },

  /** GET /ems/permissions — requires system.view. */
  permissions(): Promise<EmsPermission[]> {
    return emsHttp.get<EmsPermission[]>('/permissions');
  },
};

export const dashboardService = {
  /** GET /ems/dashboard — summary, upcoming events, activity and quick actions. */
  show(): Promise<EmsDashboard> {
    return emsHttp.get<EmsDashboard>('/dashboard');
  },
};
