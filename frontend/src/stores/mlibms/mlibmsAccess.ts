import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth';

export const useMlibmsAccessStore = defineStore('mlibmsAccess', {
  state: () => ({
    resolved: false,
  }),

  getters: {
    hasMlibmsAccess(): boolean {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) return false;

      // Super Admin or Admin role bypass
      if (authStore.isPrivilegedAdmin || authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
        return true;
      }

      // Explicit application_access grant
      if (authStore.user?.application_access?.mlibms?.access === true) {
        return true;
      }

      // MLibMS staff roles
      if (
        authStore.roles.includes('mlibms-administrator') ||
        authStore.roles.includes('mlibms-cataloger') ||
        authStore.roles.includes('mlibms-viewer')
      ) {
        return true;
      }

      // MLibMS permissions
      const mlibmsPermissions = [
        'library.view',
        'library.catalog',
        'library.copies',
        'library.members',
        'library.loans',
        'library.reservations',
        'library.reports',
        'library.settings',
        'library.export',
      ];
      return mlibmsPermissions.some((perm) => authStore.permissions.includes(perm));
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
