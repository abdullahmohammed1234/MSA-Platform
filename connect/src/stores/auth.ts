import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authService } from '@/services/auth.service';
import { accountService } from '@/services/account.service';
import { AUTH_TOKEN_KEY } from '@/services/api';
import { applyRenewedAccessToken } from '@/utils/accessToken';
import { signOut } from '@/utils/signOut';
import { useDashboardStore } from './dashboard';
import { useTicketsStore } from './tickets';
import { useEventsStore } from './events';
import { useNotificationsStore } from './notifications';
import { useSupportStore } from './support';
import { useMessagingStore } from './messaging';
import type { User, LoginPayload } from '@/types/auth';
import type { UpdateProfilePayload } from '@/types/account';

function getStoredToken(): string | null {
  try {
    return localStorage.getItem(AUTH_TOKEN_KEY);
  } catch {
    return null;
  }
}

export const useAuthStore = defineStore('connect_auth', () => {
  const token = ref<string | null>(getStoredToken());
  const user = ref<User | null>(null);
  const isLoading = ref<boolean>(false);
  const isInitialized = ref<boolean>(false);
  let restorePromise: Promise<boolean> | null = null;

  // Computeds
  const isAuthenticated = computed<boolean>(() => !!user.value);
  const roles = computed<string[]>(() => user.value?.roles ?? []);
  const permissions = computed<string[]>(() => user.value?.permissions ?? []);
  const isVerified = computed<boolean>(() => user.value?.is_verified ?? false);

  /**
   * Set or clear active session and storage.
   */
  function setSession(newToken: string | null, newUser: User | null) {
    token.value = newToken;
    user.value = newUser;

    try {
      if (newToken && newToken !== 'sso_session') {
        localStorage.setItem(AUTH_TOKEN_KEY, newToken);
      } else {
        localStorage.removeItem(AUTH_TOKEN_KEY);
      }
    } catch (e) {
      console.warn('Storage operation failed:', e);
    }
  }

  /**
   * Purge private member state across all stores to prevent cross-account data leakage.
   */
  function purgeAllUserData() {
    setSession(null, null);

    try {
      useDashboardStore().clearState();
      useTicketsStore().clearState();
      useEventsStore().clearState();
      useNotificationsStore().clearState();
      useSupportStore().clearState();
      useMessagingStore().clearState();
    } catch (e) {
      console.warn('Error clearing sub-stores on logout:', e);
    }
  }

  /**
   * Authenticate user against central platform API.
   */
  async function login(payload: LoginPayload): Promise<void> {
    isLoading.value = true;
    try {
      const data = await authService.login(payload);
      setSession(data.token, data.user);
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Verify and restore session on application mount.
   * Leverages shared Sanctum session cookies across origins.
   * Deduplicates concurrent probe requests so only a single /auth/me call executes.
   */
  async function restoreSession(): Promise<boolean> {
    if (isInitialized.value) {
      return isAuthenticated.value;
    }
    if (restorePromise) {
      return await restorePromise;
    }

    isLoading.value = true;
    restorePromise = (async () => {
      try {
        const data = await authService.me();
        user.value = data.user;
        if (!token.value) {
          token.value = 'sso_session';
        }
        return true;
      } catch {
        // Invalidation or expired session
        purgeAllUserData();
        return false;
      } finally {
        isLoading.value = false;
        isInitialized.value = true;
        restorePromise = null;
      }
    })();

    return await restorePromise;
  }

  /**
   * Update member profile (name, email) and sync local state.
   */
  async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
    const response = await accountService.updateProfile(payload);
    user.value = response.user;
    return response.user;
  }

  /**
   * Log user out server-authoritatively and clear local state across all stores.
   */
  async function logout(): Promise<boolean> {
    isLoading.value = true;
    try {
      return await signOut(() => authService.logout(), purgeAllUserData);
    } finally {
      isInitialized.value = true;
      isLoading.value = false;
    }
  }

  /**
   * Explicitly purge session and store state.
   */
  function clearSession() {
    purgeAllUserData();
  }

  /**
   * Store the replacement personal access token from a successful password change.
   * Does not clear the signed-in user or other device sessions.
   */
  function replaceToken(renewed: string | null | undefined) {
    if (!applyRenewedAccessToken(localStorage, AUTH_TOKEN_KEY, renewed)) {
      return;
    }
    token.value = renewed ?? null;
  }

  // Register listener for 401 unauthorized events and window focus to sync cross-app session state
  if (typeof window !== 'undefined') {
    window.addEventListener('msa:unauthorized', () => {
      purgeAllUserData();
    });

    window.addEventListener('focus', async () => {
      if (user.value) {
        try {
          await authService.me();
        } catch {
          purgeAllUserData();
        }
      }
    });
  }

  return {
    token,
    user,
    isLoading,
    isInitialized,
    isAuthenticated,
    roles,
    permissions,
    isVerified,
    login,
    restoreSession,
    updateProfile,
    logout,
    clearSession,
    replaceToken,
    purgeAllUserData,
  };
});
