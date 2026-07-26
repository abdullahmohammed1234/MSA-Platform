import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_BASE_PATH } from '@/constants/ems';

/**
 * Route protection for the EMS shell.
 *
 * Runs ahead of the platform guards and fully owns /ems, because the generic
 * permissionGuard falls back to the academy dashboard on failure, which is the
 * wrong destination for an EMS user.
 *
 * Three decisions, in order:
 *   1. no platform session      -> the platform login, with a return path
 *   2. session but no EMS grant -> the EMS "no access" screen
 *   3. missing route permission -> the EMS "no access" screen
 *
 * This is navigation only. Every route it admits still has each of its actions
 * authorized server-side.
 */
export const emsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith(EMS_BASE_PATH)) {
    return true;
  }

  // The "no access" screen must stay reachable or the redirect below loops.
  if (to.meta.emsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useEmsAccessStore();
  await access.resolve();

  if (!access.hasEmsAccess) {
    return { name: 'ems-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.emsPermissions;

    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];

    return [];
  });

  if (required.length > 0 && !access.canAny(required)) {
    return { name: 'ems-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
