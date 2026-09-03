import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useAppAccess() {
  const authStore = useAuthStore();

  const isSuperOrAdmin = computed(() => {
    if (typeof authStore.isPrivilegedAdmin === 'boolean') {
      return authStore.isPrivilegedAdmin;
    }
    return authStore.roles.includes('admin') || authStore.roles.includes('super-admin');
  });

  const hasCmsAccess = computed(() => {
    if (authStore.user?.application_access?.cms) {
      return authStore.user.application_access.cms.access;
    }
    return isSuperOrAdmin.value;
  });

  const hasDamsAccess = computed(() => {
    if (authStore.user?.application_access?.dams) {
      return authStore.user.application_access.dams.access;
    }
    return isSuperOrAdmin.value;
  });

  const hasEmsAccess = computed(() => {
    if (authStore.user?.application_access?.ems) {
      return authStore.user.application_access.ems.access;
    }
    return isSuperOrAdmin.value;
  });

  const hasStoreAccess = computed(() => {
    if (authStore.user?.application_access?.store) {
      return authStore.user.application_access.store.access;
    }
    return isSuperOrAdmin.value || authStore.roles.includes('store-administrator') || authStore.permissions.includes('store.products.view');
  });

  const hasAdminAccess = computed(() => {
    if (authStore.user?.application_access?.['admin-portal']) {
      return authStore.user.application_access['admin-portal'].access;
    }
    return isSuperOrAdmin.value;
  });

  return {
    hasCmsAccess,
    hasDamsAccess,
    hasEmsAccess,
    hasStoreAccess,
    hasAdminAccess,
  };
}
