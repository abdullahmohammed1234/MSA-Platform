import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useConnectAdminAccessStore } from '@/stores/connect/connectAdminAccess';

export const connectAdminGuard = async (
  to: RouteLocationNormalized
): Promise<boolean | RouteLocationRaw> => {
  if (!to.path.startsWith('/connect/admin')) {
    return true;
  }

  if (to.meta.connectPublic === true) {
    return true;
  }

  const authStore = useAuthStore();

  if (!authStore.token) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const access = useConnectAdminAccessStore();
  await access.resolve();

  if (!access.hasConnectAccess) {
    return { name: 'connect-admin-unauthorized', query: { from: to.fullPath } };
  }

  const required = to.matched.flatMap((record) => {
    const meta = record.meta.connectPermissions;
    if (Array.isArray(meta)) return meta as string[];
    if (typeof meta === 'string') return [meta];
    return [];
  });

  if (to.name !== 'connect-admin-dashboard' && required.length > 0 && !access.canAny(required)) {
    return { name: 'connect-admin-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
