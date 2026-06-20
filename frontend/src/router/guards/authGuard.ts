import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const authGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  
  if (requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  return true;
};
