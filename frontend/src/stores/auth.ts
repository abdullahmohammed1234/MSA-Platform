import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authService } from '@/services/auth/auth.service';
import { userService } from '@/services/user/userService';
import type { 
  User, 
  LoginPayload, 
  RegisterPayload, 
  ResetPasswordPayload 
} from '@/types/auth';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'));
  const user = ref<User | null>(
    localStorage.getItem('auth_user') 
      ? JSON.parse(localStorage.getItem('auth_user')!) 
      : null
  );
  const isLoading = ref<boolean>(false);

  // Computeds
  const isAuthenticated = computed(() => !!token.value && !!user.value);
  const roles = computed(() => user.value?.roles || []);
  const permissions = computed(() => user.value?.permissions || []);
  const isVerified = computed(() => user.value?.is_verified || false);
  const requiresEmailVerification = computed(
    () => user.value?.requires_email_verification ?? (
      roles.value.includes('member') || roles.value.includes('volunteer')
    )
  );
  const needsEmailVerification = computed(
    () => requiresEmailVerification.value && !isVerified.value
  );

  const isPrivilegedAdmin = computed(() => roles.value.includes('admin') || roles.value.includes('super-admin'));
  const isAdmin = isPrivilegedAdmin;
  const isDirector = computed(() => roles.value.includes('director') || isPrivilegedAdmin.value);
  const isMentor = computed(() => roles.value.includes('mentor') || isPrivilegedAdmin.value);
  const isVolunteer = computed(() => roles.value.includes('volunteer') || isPrivilegedAdmin.value);
  const isMember = computed(() => roles.value.includes('member'));
  const canAccessAcademy = computed(() => isVolunteer.value || isMentor.value);

  const canManageCourses = computed(() => permissions.value.includes('manage_courses') || isPrivilegedAdmin.value);
  const canManageUsers = computed(() => permissions.value.includes('manage_users') || isPrivilegedAdmin.value);
  const canViewAnalytics = computed(() => permissions.value.includes('view_analytics') || isPrivilegedAdmin.value);

  const getEffectiveRole = (userRoles: string[] = []) => {
    if (userRoles.includes('admin') || userRoles.includes('super-admin')) {
      return 'admin';
    }

    return userRoles[0] || 'student';
  };

  // Set auth state
  const setSession = (newToken: string | null, newUser: User | null) => {
    token.value = newToken;
    user.value = newUser;

    if (newToken) {
      localStorage.setItem('auth_token', newToken);
    } else {
      localStorage.removeItem('auth_token');
    }

    if (newUser) {
      localStorage.setItem('auth_user', JSON.stringify(newUser));
      localStorage.setItem('user_role', getEffectiveRole(newUser.roles));
    } else {
      localStorage.removeItem('auth_user');
      localStorage.removeItem('user_role');
    }
  };

  /**
   * Log user in.
   */
  const login = async (payload: LoginPayload) => {
    isLoading.value = true;
    try {
      const data = await authService.login(payload);
      setSession(data.token || null, data.user);
      return data;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Register a new user.
   */
  const register = async (payload: RegisterPayload) => {
    isLoading.value = true;
    try {
      const data = await authService.register(payload);
      // If the API logs the user in directly, store token. Otherwise, verify-email will follow.
      if (data.token) {
        setSession(data.token, data.user);
      }
      return data;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Log active user out.
   */
  const logout = async () => {
    isLoading.value = true;
    try {
      await authService.logout();
    } catch (error) {
      console.error('Logout API request failed', error);
    } finally {
      setSession(null, null);
      isLoading.value = false;
    }
  };

  /**
   * Fetch user details from /me.
   */
  const fetchUser = async () => {
    if (!token.value) return null;
    isLoading.value = true;
    try {
      const data = await authService.getMe();
      setSession(token.value, data.user);
      return data.user;
    } catch (error) {
      setSession(null, null); // Clear state on failed profile fetch
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Restore user session on application initialization.
   */
  const restoreSession = async () => {
    if (token.value) {
      try {
        await fetchUser();
      } catch (error) {
        console.warn('Session restoration failed', error);
      }
    }
  };

  /**
   * Request verification email.
   */
  const resendVerification = async () => {
    isLoading.value = true;
    try {
      return await authService.resendVerification();
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Verify email using token.
   */
  const verifyEmail = async (verificationToken: string) => {
    isLoading.value = true;
    try {
      const result = await authService.verifyEmail(verificationToken);
      // Re-fetch user profile to sync the verified status
      if (token.value) {
        await fetchUser();
      }
      return result;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Request password reset link.
   */
  const forgotPassword = async (email: string) => {
    isLoading.value = true;
    try {
      return await authService.forgotPassword(email);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Reset password with token.
   */
  const resetPassword = async (payload: ResetPasswordPayload) => {
    isLoading.value = true;
    try {
      return await authService.resetPassword(payload);
    } finally {
      isLoading.value = false;
    }
  };

  const updateProfile = async (payload: { name?: string; email?: string }) => {
    isLoading.value = true;
    try {
      const updatedUser = await userService.updateProfile(payload);
      setSession(token.value, updatedUser);
      return updatedUser;
    } finally {
      isLoading.value = false;
    }
  };

  const completeAcademyOnboarding = async () => {
    const updatedUser = await userService.completeAcademyOnboarding();
    setSession(token.value, updatedUser);
    return updatedUser;
  };

  // Sync token clearing from Axios interceptors
  if (typeof window !== 'undefined') {
    window.addEventListener('auth:unauthorized', () => {
      token.value = null;
      user.value = null;
    });
  }

  return {
    token,
    user,
    isLoading,
    isAuthenticated,
    roles,
    permissions,
    isVerified,
    requiresEmailVerification,
    needsEmailVerification,
    isPrivilegedAdmin,
    isAdmin,
    isDirector,
    isMentor,
    isVolunteer,
    isMember,
    canAccessAcademy,
    canManageCourses,
    canManageUsers,
    canViewAnalytics,
    login,
    register,
    logout,
    fetchUser,
    restoreSession,
    resendVerification,
    verifyEmail,
    forgotPassword,
    resetPassword,
    updateProfile,
    completeAcademyOnboarding,
  };
});
