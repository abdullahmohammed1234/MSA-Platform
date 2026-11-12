import { defineStore } from 'pinia';
import apiClient, { PM_TOKEN_KEY, PM_USER_KEY } from '../api/client';
import { projectsApi } from '../api/projects';
import type { DepartmentSummary, ProjectMember, UserSummary } from '../types/pm';

export const useAuthStore = defineStore('pmAuth', {
  state: () => ({
    user: (localStorage.getItem(PM_USER_KEY) ? JSON.parse(localStorage.getItem(PM_USER_KEY)!) : null) as UserSummary | null,
    token: localStorage.getItem(PM_TOKEN_KEY) || null,
    departments: [] as DepartmentSummary[],
    pendingInvitations: [] as ProjectMember[],
    isLoading: false,
    isExchangingSso: false,
    ssoError: null as string | null,
    isForbidden: false,
    forbiddenReason: null as string | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    userInitials: (state) => {
      if (!state.user?.name) return 'MSA';
      const parts = state.user.name.split(' ');
      return parts.length > 1 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].slice(0, 2).toUpperCase();
    },
    hasPendingInvitations: (state) => state.pendingInvitations.length > 0,
  },

  actions: {
    /**
     * Initialize authentication, check for secure SSO ticket in URL fragment (SEC-11-01),
     * and verify PMS application access.
     */
    async initAuth(): Promise<boolean> {
      // 1. Check for single-use SSO ticket in URL fragment (#sso_ticket=...)
      let ssoTicket: string | null = null;

      if (window.location.hash) {
        const hashStr = window.location.hash.startsWith('#')
          ? window.location.hash.substring(1)
          : window.location.hash;
        const hashParams = new URLSearchParams(hashStr);
        if (hashParams.has('sso_ticket')) {
          ssoTicket = hashParams.get('sso_ticket');
          hashParams.delete('sso_ticket');
          const remainingHash = hashParams.toString();
          // Immediately scrub ticket from visible URL history to prevent exposure
          const cleanUrl = window.location.pathname + window.location.search + (remainingHash ? `#${remainingHash}` : '');
          window.history.replaceState({}, document.title, cleanUrl);
        }
      }

      // Check query params: sanitize stray tokens and check sso_ticket
      if (window.location.search) {
        const urlParams = new URLSearchParams(window.location.search);
        let urlModified = false;

        // SEC-11-01: Explicitly scrub any legacy 'token' query param without adopting it
        if (urlParams.has('token')) {
          urlParams.delete('token');
          urlModified = true;
        }

        if (!ssoTicket && urlParams.has('sso_ticket')) {
          ssoTicket = urlParams.get('sso_ticket');
          urlParams.delete('sso_ticket');
          urlModified = true;
        }

        if (urlModified) {
          const cleanQuery = urlParams.toString();
          const cleanUrl = window.location.pathname + (cleanQuery ? `?${cleanQuery}` : '') + window.location.hash;
          window.history.replaceState({}, document.title, cleanUrl);
        }
      }

      // 2. Exchange SSO ticket atomically if found
      if (ssoTicket && !this.isExchangingSso) {
        this.isExchangingSso = true;
        this.isLoading = true;
        this.ssoError = null;

        try {
          const res = await projectsApi.exchangeSsoTicket(ssoTicket);
          if (res.token) {
            this.setToken(res.token);
            if (res.user) {
              this.user = res.user;
              localStorage.setItem(PM_USER_KEY, JSON.stringify(this.user));
            }
          }
        } catch (err: any) {
          console.error('SSO ticket exchange failed:', err);
          const errorMsg = err.response?.data?.message || 'SSO authentication failed or ticket has expired. Please sign in below.';
          this.ssoError = errorMsg;
          // Invalidate any stale credentials
          this.token = null;
          this.user = null;
          localStorage.removeItem(PM_TOKEN_KEY);
          localStorage.removeItem(PM_USER_KEY);
        } finally {
          this.isExchangingSso = false;
        }
      }

      if (!this.token) {
        return false;
      }

      this.isLoading = true;
      this.isForbidden = false;
      this.forbiddenReason = null;

      try {
        // Fetch dashboard to confirm application access and load user context
        const dashboard = await projectsApi.getDashboard();
        this.user = dashboard.user;
        this.departments = dashboard.departments;
        localStorage.setItem(PM_USER_KEY, JSON.stringify(this.user));

        // Load any pending invitations
        await this.fetchPendingInvitations();

        return true;
      } catch (err: any) {
        if (err.response?.status === 403) {
          this.isForbidden = true;
          this.forbiddenReason = err.response?.data?.message || 'PMS Access Denied: You must be a formally appointed SFU MSA team member to access this system.';
          return false;
        } else if (err.response?.status === 401) {
          this.logout();
          return false;
        }
        return false;
      } finally {
        this.isLoading = false;
      }
    },

    setToken(token: string) {
      this.token = token;
      localStorage.setItem(PM_TOKEN_KEY, token);
    },

    async login(email: string, password: string): Promise<boolean> {
      this.isLoading = true;
      this.isForbidden = false;
      this.forbiddenReason = null;
      this.ssoError = null;

      try {
        const res = await apiClient.post('/auth/login', { email, password });
        const token = res.data.token || res.data.access_token;
        if (!token) {
          throw new Error('No authentication token returned by server.');
        }

        this.setToken(token);
        return await this.initAuth();
      } catch (err: any) {
        if (err.response?.status === 403) {
          this.isForbidden = true;
          this.forbiddenReason = err.response?.data?.message || 'Access Denied: Your account does not have Project Management privileges.';
        }
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    async logout() {
      try {
        if (this.token) {
          await apiClient.post('/auth/logout');
        }
      } catch {
        // Ignore logout errors
      } finally {
        this.token = null;
        this.user = null;
        this.departments = [];
        this.pendingInvitations = [];
        this.isForbidden = false;
        this.forbiddenReason = null;
        this.ssoError = null;
        localStorage.removeItem(PM_TOKEN_KEY);
        localStorage.removeItem(PM_USER_KEY);
      }
    },

    async fetchPendingInvitations() {
      if (!this.token) return;
      try {
        this.pendingInvitations = await projectsApi.getMyInvitations();
      } catch {
        this.pendingInvitations = [];
      }
    },

    async acceptInvitation(memberId: number) {
      await projectsApi.acceptInvitation(memberId);
      await this.fetchPendingInvitations();
    },

    async declineInvitation(memberId: number) {
      await projectsApi.declineInvitation(memberId);
      await this.fetchPendingInvitations();
    },
  },
});
