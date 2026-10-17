import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth';

export const useConnectAdminAccessStore = defineStore('connectAdminAccess', {
  state: () => ({
    resolved: false,
  }),

  getters: {
    hasConnectAccess(): boolean {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) return false;

      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }

      if (authStore.user?.application_access?.connect?.access === true) {
        return true;
      }

      if (
        authStore.roles.includes('connect-administrator') ||
        authStore.roles.includes('connect-staff')
      ) {
        return true;
      }

      const connectPermissions = [
        'connect.view',
        'connect.manage',
        'system.view',
        'system.manage',
        'view_systems_health',
      ];
      return connectPermissions.some((perm) => authStore.permissions.includes(perm));
    },

    canManage(): boolean {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) return false;

      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }

      if (authStore.roles.includes('connect-administrator')) {
        return true;
      }

      return authStore.permissions.includes('connect.manage') || authStore.permissions.includes('system.manage');
    },
  },

  actions: {
    async resolve() {
      const authStore = useAuthStore();
      if (authStore.token && !authStore.user) {
        await authStore.fetchUser();
      }
      this.resolved = true;
    },

    canAny(permissions: string[]): boolean {
      const authStore = useAuthStore();
      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }
      return permissions.some((p) => authStore.permissions.includes(p));
    },
  },
});
