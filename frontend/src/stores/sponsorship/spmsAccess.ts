import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth';

export const useSpmsAccessStore = defineStore('spmsAccess', {
  state: () => ({
    resolved: false,
  }),

  getters: {
    hasSpmsAccess(): boolean {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) return false;

      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }

      if (authStore.user?.application_access?.sponsorship?.access === true) {
        return true;
      }

      if (
        authStore.roles.includes('spms-administrator') ||
        authStore.roles.includes('spms-staff') ||
        authStore.roles.includes('spms-viewer')
      ) {
        return true;
      }

      const spmsPermissions = [
        'sponsorship.view',
        'sponsorship.manage',
        'sponsorship.agreements',
        'sponsorship.payments',
        'sponsorship.fulfillment',
        'sponsorship.reports',
        'sponsorship.export',
      ];
      return spmsPermissions.some((perm) => authStore.permissions.includes(perm));
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
