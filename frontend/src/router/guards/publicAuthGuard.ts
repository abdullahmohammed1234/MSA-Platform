import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { isPublicAuthEnabled } from '@/config/features';

export const publicAuthGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  if (isPublicAuthEnabled) {
    return true;
  }

  const isPublicAuthRoute = to.matched.some(record => record.meta.publicAuth === true);
  if (isPublicAuthRoute) {
    return { name: 'home' };
  }

  return true;
};
