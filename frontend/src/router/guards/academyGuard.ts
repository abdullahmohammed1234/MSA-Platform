import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { isAcademyEnabled } from '@/config/features';

export const academyGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  if (isAcademyEnabled) {
    return true;
  }

  if (to.path.startsWith('/academy')) {
    return { name: 'home' };
  }

  return true;
};
