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
    const hasRequiredPermission = requiredPermissions.some(permission => 
      authStore.permissions.includes(permission) || (
        typeof authStore.isPrivilegedAdmin === 'boolean'
          ? authStore.isPrivilegedAdmin
          : authStore.roles.includes('admin') || authStore.roles.includes('super-admin')
      )
    );
    
    if (!authStore.isAuthenticated || !hasRequiredPermission) {
      return { name: 'academy-dashboard' };
    }
  }

  return true;
};
