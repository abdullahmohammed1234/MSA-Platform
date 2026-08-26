import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import client from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import type { DamsCurrentUser } from '@/types/academy';

export const useDamsAccessStore = defineStore('damsAccess', () => {
  const profile = ref<DamsCurrentUser | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const isResolved = ref(false);

  const permissions = computed<string[]>(() => profile.value?.permissions ?? []);
  const roles = computed(() => profile.value?.roles ?? []);
  const hasDamsAccess = computed(() => profile.value?.has_dams_access === true);

  const can = (permission: string): boolean => permissions.value.includes(permission);

  const canAny = (required: string[]): boolean =>
    required.length === 0 || required.some((permission) => can(permission));

  const canAll = (required: string[]): boolean => required.every((permission) => can(permission));

  const resolve = async (force = false): Promise<DamsCurrentUser | null> => {
    const authStore = useAuthStore();

    if (!authStore.token) {
      reset();
      isResolved.value = true;
      return null;
    }

    if (isResolved.value && !force && profile.value) {
      return profile.value;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const response = await client.get('/dams/users/me');
      profile.value = response.data.data;
      return profile.value;
    } catch (caught: any) {
      profile.value = null;
      error.value = caught.response?.data?.message || 'Failed to resolve DAMS access.';
      return null;
    } finally {
      isResolved.value = true;
      isLoading.value = false;
    }
  };

  const reset = () => {
    profile.value = null;
    error.value = null;
    isResolved.value = false;
  };

  return {
    profile,
    isLoading,
    error,
    isResolved,
    permissions,
    roles,
    hasDamsAccess,
    can,
    canAny,
    canAll,
    resolve,
    reset,
  };
});
