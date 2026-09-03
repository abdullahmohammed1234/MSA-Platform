import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth';

export const useDmsAccessStore = defineStore('dmsAccess', {
  state: () => ({
    resolved: false,
  }),

  getters: {
    hasDmsAccess(): boolean {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) return false;

      // Super Admin or Admin role
      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }

      // Explicit application_access grant
      if (authStore.user?.application_access?.donations?.access === true) {
        return true;
      }

      // DMS specific roles
      if (authStore.roles.includes('dms-administrator') || authStore.roles.includes('dms-staff')) {
        return true;
      }

      // DMS specific permissions
      const dmsPermissions = [
        'donations.view',
        'donations.manage',
        'donations.refund',
        'donations.reports',
        'donations.export',
        'donations.reconcile',
      ];
      return dmsPermissions.some((perm) => authStore.permissions.includes(perm));
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
