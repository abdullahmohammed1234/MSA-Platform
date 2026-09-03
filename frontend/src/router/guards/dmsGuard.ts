import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useDmsAccessStore } from '@/stores/donations/dmsAccess';

export const dmsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/donations/admin') && !to.path.startsWith('/dms')) {
    return true;
  }

  // The unauthorized screen must stay reachable to prevent infinite loops
  if (to.meta.dmsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useDmsAccessStore();
  await access.resolve();

  if (!access.hasDmsAccess) {
    return { name: 'dms-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.dmsPermissions;
    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];
    return [];
  });

  if (to.name !== 'dms-dashboard' && required.length > 0 && !access.canAny(required)) {
    return { name: 'dms-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
