import type { RouteLocationNormalized } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useVmsAccessStore } from '@/stores/vms/vmsAccess';

export const vmsGuard = async (
  to: RouteLocationNormalized
): Promise<true | { name: string; query?: Record<string, string> }> => {
  if (!to.path.startsWith('/vms')) {
    return true;
  }

  if (to.meta.vmsPublic === true) {
    return true;
  }

  const authStore = useAuthStore();
  if (!authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  const vmsAccess = useVmsAccessStore();
  await vmsAccess.resolve();

  if (authStore.isPrivilegedAdmin) {
    return true;
  }

  const appAccess = authStore.user?.application_access?.vms?.access ?? authStore.user?.application_access?.volunteering?.access;
  if (appAccess === true) {
    return true;
  }

  const required = (to.meta.vmsPermissions as string[]) || [];
  if (required.length > 0 && !vmsAccess.canAny(required)) {
    return { name: 'vms-unauthorized', query: { from: to.fullPath } };
  }

  return true;
};
