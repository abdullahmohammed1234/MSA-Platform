import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const guestGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();
  const guestOnly = to.matched.some(record => record.meta.guestOnly);

  if (guestOnly && authStore.isAuthenticated) {
    if (!authStore.isVerified) {
      return { name: 'verify-email' };
    }

    if (authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
      return { name: 'admin-dashboard' };
    }
    if (authStore.canAccessAcademy) {
      return { name: 'academy-dashboard' };
    }
    return { name: 'home' };
  }

  return true;
};
