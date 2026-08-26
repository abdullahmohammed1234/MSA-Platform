import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useDamsAccessStore } from '@/stores/dams/damsAccess';

export const damsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/dams')) {
    return true;
  }

  // The "no access" screen must stay reachable or the redirect below loops.
  if (to.meta.damsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useDamsAccessStore();
  await access.resolve();

  if (!access.hasDamsAccess) {
    return { name: 'dams-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.damsPermissions;

    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];

    return [];
  });

  if (required.length > 0 && !access.canAny(required)) {
    return { name: 'dams-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
