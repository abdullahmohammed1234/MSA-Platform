import axios from 'axios';
import { getApiBaseUrl } from '@/config/api.js';

const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  withXSRFToken: true,
});

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');

    if (token && token !== 'sso_session') {
      config.headers.Authorization = `Bearer ${token}`;
    } else if (config.headers?.Authorization?.includes('sso_session')) {
      delete config.headers.Authorization;
    }

    return config;
  },
  (error) => Promise.reject(error),
);

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status ?? null;

    if (error.code === 'ECONNABORTED') {
      error.message = 'The server took too long to respond. Please try again.';
    } else if (!error.response || error.code === 'ERR_NETWORK') {
      error.message = 'Unable to reach the API server. Check your connection and try again.';
    }

    if (status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      localStorage.removeItem('user_role');

      window.dispatchEvent(new CustomEvent('auth:unauthorized'));

      const isAuthProbe = error.config?.url?.includes('/auth/me');
      const isPublicOrAuthPage =
        typeof window !== 'undefined' &&
        (window.location.pathname === '/login' ||
          window.location.pathname === '/' ||
          window.location.pathname === '');

      if (!isAuthProbe && !isPublicOrAuthPage && typeof window !== 'undefined') {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
      }
    }

    return Promise.reject(error);
  },
);

export default api;
