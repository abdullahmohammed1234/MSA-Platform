import axios, { type AxiosInstance, type AxiosError } from 'axios';
import { getApiBaseUrl } from '../config/api.ts';
import { isAllowedApiDestination } from '../utils/security.ts';
import type { ApiError } from '../types/api.ts';

/**
 * Storage key for Sanctum personal access token.
 * Prefixed to isolate Connect from other applications sharing localStorage if on same origin.
 */
export const AUTH_TOKEN_KEY = 'msa_connect_token';

/**
 * Typed Axios instance configured for Laravel Sanctum API.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: false,
  withXSRFToken: false,
});

// Request Interceptor: Attach Sanctum Bearer token strictly to the configured API origin
apiClient.interceptors.request.use(
  (config) => {
    try {
      const token = localStorage.getItem(AUTH_TOKEN_KEY);
      if (token && config.headers) {
        // Enforce origin boundary: only attach Bearer tokens to authorized API origin
        if (isAllowedApiDestination(config.url, config.baseURL)) {
          config.headers.Authorization = `Bearer ${token}`;
        } else {
          // Explicitly strip Authorization header if targeting an external origin
          delete config.headers.Authorization;
        }
      }
    } catch {
      // Storage access blocked or restricted; proceed without token
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// Response Interceptor: Safe error normalization and 401 handling
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const status = error.response?.status;
    const responseData = error.response?.data;

    const normalizedError: ApiError = {
      message: 'An unexpected error occurred. Please try again.',
      statusCode: status,
      errors: responseData?.errors,
    };

    if (error.code === 'ECONNABORTED') {
      normalizedError.message = 'The server took too long to respond. Please check your connection and try again.';
    } else if (!error.response || error.code === 'ERR_NETWORK') {
      normalizedError.message = 'Unable to reach the server. Please verify your internet connection.';
    } else if (responseData?.message) {
      normalizedError.message = responseData.message;
    } else if (status === 401) {
      normalizedError.message = 'Your session has expired or is invalid. Please sign in again.';
    } else if (status === 403) {
      normalizedError.message = 'You do not have permission to perform this action.';
    } else if (status === 404) {
      normalizedError.message = 'The requested resource was not found.';
    } else if (status === 422) {
      normalizedError.message = responseData?.message || 'Please check the information provided and correct any errors.';
    } else if (status && status >= 500) {
      // Mask internal server error details to prevent leaking sensitive stack or environment information
      normalizedError.message = 'A server error occurred. Our team has been notified. Please try again shortly.';
    }

    // Handle 401 Unauthorized: Purge token and notify application (avoiding redirect loops if already on login)
    if (status === 401) {
      try {
        localStorage.removeItem(AUTH_TOKEN_KEY);
      } catch {}

      window.dispatchEvent(new CustomEvent('msa:unauthorized'));

      // Only redirect if not an auth probe and not already on the login or public landing route
      const isAuthProbe = error.config?.url?.includes('/auth/me');
      if (!isAuthProbe && typeof window !== 'undefined') {
        const currentPath = window.location.pathname;
        const base = (typeof import.meta !== 'undefined' && import.meta.env?.BASE_URL ? import.meta.env.BASE_URL : '/').replace(/\/$/, '');
        const loginPath = base ? `${base}/login` : '/login';
        if (currentPath !== loginPath && currentPath !== (base || '/') && currentPath !== '') {
          window.location.href = `${loginPath}?redirect=${encodeURIComponent(currentPath + window.location.search)}`;
        }
      }
    }

    return Promise.reject(normalizedError);
  },
);

export default apiClient;
