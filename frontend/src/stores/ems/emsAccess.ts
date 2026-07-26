import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { accessService, toEmsApiError } from '@/services/ems';
import { useAuthStore } from '@/stores/auth';
import type { EmsCurrentUser } from '@/types/ems';

/**
 * The viewer's EMS access model.
 *
 * Authentication itself belongs to the platform auth store — this store never
 * holds a token or a password. It answers one question: given the session the
 * platform already established, what may this user do inside the EMS?
 *
 * The answer comes from GET /ems/users/me rather than from the platform user's
 * global permission list, so the EMS shell is driven by EMS capabilities only.
 */
export const useEmsAccessStore = defineStore('emsAccess', () => {
  const profile = ref<EmsCurrentUser | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  /** Null until the access model has been fetched at least once. */
  const isResolved = ref(false);

  const permissions = computed<string[]>(() => profile.value?.permissions ?? []);
  const roles = computed(() => profile.value?.roles ?? []);
  const hasEmsAccess = computed(() => profile.value?.has_ems_access === true);

  const can = (permission: string): boolean => permissions.value.includes(permission);

  const canAny = (required: string[]): boolean =>
    required.length === 0 || required.some((permission) => can(permission));

  const canAll = (required: string[]): boolean => required.every((permission) => can(permission));

  /**
   * Load the access model. Repeat calls are no-ops unless `force` is set, so
   * the route guard can call this on every navigation cheaply.
   */
  const resolve = async (force = false): Promise<EmsCurrentUser | null> => {
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
      profile.value = await accessService.me();
      return profile.value;
    } catch (caught) {
      const apiError = toEmsApiError(caught);

      // A 401 means the platform session went away; the shared axios
      // interceptor already handles the redirect, so only record the reason.
      profile.value = null;
      error.value = apiError.message;

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
    hasEmsAccess,
    can,
    canAny,
    canAll,
    resolve,
    reset,
  };
});
