import { useAuthStore } from '@/stores/auth';

export function useAuthorization() {
  const authStore = useAuthStore();
  const hasAdminAccess = () => {
    if (typeof authStore.isPrivilegedAdmin === 'boolean') {
      return authStore.isPrivilegedAdmin;
    }

    return authStore.roles.includes('admin') || authStore.roles.includes('super-admin');
  };

  /**
   * Check if user has a specific role.
   * Privileged admins match everything.
   */
  const hasRole = (role: string): boolean => {
    return authStore.roles.includes(role) || hasAdminAccess();
  };

  /**
   * Check if user has a specific permission.
   * Privileged admins match everything.
   */
  const hasPermission = (permission: string): boolean => {
    return authStore.permissions.includes(permission) || hasAdminAccess();
  };

  /**
   * Alias for hasPermission.
   */
  const can = (permission: string): boolean => {
    return hasPermission(permission);
  };

  return {
    can,
    hasRole,
    hasPermission,
  };
}
