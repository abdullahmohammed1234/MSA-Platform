import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCmsAccessStore } from '@/stores/cms/cmsAccess';

export const cmsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/cms')) {
    return true;
  }

  // The "no access" screen must stay reachable or the redirect below loops.
  if (to.meta.cmsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useCmsAccessStore();
  await access.resolve();

  if (!access.hasCmsAccess) {
    return { name: 'cms-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.cmsPermissions;

    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];

    return [];
  });

  if (to.name !== 'cms-dashboard' && required.length > 0 && !access.canAny(required)) {
    return { name: 'cms-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
