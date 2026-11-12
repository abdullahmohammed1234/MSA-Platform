import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const permissionGuard = (
  to: RouteLocationNormalized
): boolean | RouteLocationRaw => {
  const authStore = useAuthStore();
  
  const requiredPermissions = to.matched.flatMap(record => {
    const permissionsMeta = record.meta.permissions;
    if (Array.isArray(permissionsMeta)) return permissionsMeta;
    if (typeof permissionsMeta === 'string') return [permissionsMeta];
    return [];
  });

  if (requiredPermissions.length > 0) {
    const isPrivileged =
      Boolean(authStore.isPrivilegedAdmin) ||
      (Array.isArray(authStore.roles) && (
        authStore.roles.includes('admin') ||
        authStore.roles.includes('super-admin')
      ));

    const hasRequiredPermission =
      isPrivileged ||
      requiredPermissions.some(permission => authStore.permissions.includes(permission));
    
    if (!authStore.isAuthenticated) {
      return to.path.startsWith('/admin') ? { path: '/admin' } : { name: 'home' };
    }

    if (!hasRequiredPermission) {
      if (to.path && to.path.startsWith('/admin')) {
        return { name: 'admin-dashboard' };
      }
      return { name: 'academy-dashboard' };
    }
  }

  return true;
};
