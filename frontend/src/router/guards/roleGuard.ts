import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { isAcademyEnabled } from '@/config/features';
import { useAuthStore } from '@/stores/auth';

export const roleGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();
  
  const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin);
  const requiresMentor = to.matched.some(record => record.meta.requiresMentor);
  const requiresStudent = to.matched.some(record => record.meta.requiresStudent);
  
  const requiredRoles = to.matched.flatMap(record => {
    const rolesMeta = record.meta.roles;
    if (Array.isArray(rolesMeta)) return rolesMeta;
    if (typeof rolesMeta === 'string') return [rolesMeta];
    return [];
  });

  const hasPrivilegedAdminAccess =
    typeof authStore.isPrivilegedAdmin === 'boolean'
      ? authStore.isPrivilegedAdmin
      : authStore.roles.includes('admin') || authStore.roles.includes('super-admin');

  if (authStore.isAuthenticated && hasPrivilegedAdminAccess) {
    return true;
  }

  if (requiresAdmin) {
    return isAcademyEnabled ? { name: 'academy-dashboard' } : { name: 'home' };
  }

  if (requiresMentor) {
    if (!authStore.isAuthenticated || (!authStore.isMentor && !authStore.isAdmin)) {
      return isAcademyEnabled ? { name: 'academy-dashboard' } : { name: 'home' };
    }
  }

  if (requiresStudent) {
    if (
      !authStore.isAuthenticated || 
      (!authStore.isVolunteer && 
       !authStore.isMentor && 
       !authStore.isDirector && 
       !authStore.isDawahCoordinator && 
       !authStore.isAdmin)
    ) {
      return { name: 'home' };
    }
  }

  if (requiredRoles.length > 0) {
    const hasRequiredRole = requiredRoles.some(role => authStore.roles.includes(role));
    if (!authStore.isAuthenticated || !hasRequiredRole) {
      return { name: 'home' };
    }
  }

  return true;
};
