import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const isVerifyEmailRoute = (to: RouteLocationNormalized): boolean =>
  to.name === 'verify-email';

const requiresVerification = (to: RouteLocationNormalized): boolean =>
  to.matched.some(record => record.meta.requiresVerification === true);

export const verificationGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();

  if (!authStore.isAuthenticated || authStore.isVerified) {
    return true;
  }

  if (isVerifyEmailRoute(to)) {
    return true;
  }

  if (requiresVerification(to)) {
    return { name: 'verify-email', query: { redirect: to.fullPath } };
  }

  return true;
};
