import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMlibmsAccessStore } from '@/stores/mlibms/mlibmsAccess';

export const mlibmsGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/library/admin')) {
    return true;
  }

  if (to.meta.mlibmsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useMlibmsAccessStore();
  await access.resolve();

  if (!access.hasMlibmsAccess) {
    return { name: 'mlibms-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.mlibmsPermissions;
    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];
    return [];
  });

  if (to.name !== 'mlibms-dashboard' && required.length > 0 && !access.canAny(required)) {
    return { name: 'mlibms-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
