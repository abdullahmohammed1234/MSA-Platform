import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const guestGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();
  const guestOnly = to.matched.some(record => record.meta.guestOnly);

  if (guestOnly && authStore.isAuthenticated) {
    if (authStore.needsEmailVerification) {
      return { name: 'verify-email' };
    }

    // Authenticated users on guest-only routes go to the main website.
    return { name: 'home' };
  }

  return true;
};
