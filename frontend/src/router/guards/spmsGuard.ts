import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSpmsAccessStore } from '@/stores/sponsorship/spmsAccess';

export const spmsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/sponsorship/admin') && !to.path.startsWith('/spms')) {
    return true;
  }

  if (to.meta.spmsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useSpmsAccessStore();
  await access.resolve();

  if (!access.hasSpmsAccess) {
    return { name: 'spms-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.spmsPermissions;
    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];
    return [];
  });

  if (to.name !== 'spms-dashboard' && required.length > 0 && !access.canAny(required)) {
    return { name: 'spms-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
