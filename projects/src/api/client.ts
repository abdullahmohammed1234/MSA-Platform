import axios, { type AxiosInstance } from 'axios';

export const PM_TOKEN_KEY = 'msa_pm_token';
export const PM_USER_KEY = 'msa_pm_user';

const baseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export const apiClient: AxiosInstance = axios.create({
  baseURL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
});

// Request interceptor: Attach PM Bearer token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem(PM_TOKEN_KEY);
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor: Handle 401 unauthenticated
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(PM_TOKEN_KEY);
      localStorage.removeItem(PM_USER_KEY);
      
      // Dispatch an event so the auth store or router can redirect cleanly
      window.dispatchEvent(new CustomEvent('pm-auth-unauthorized'));
    }
    return Promise.reject(error);
  }
);

export default apiClient;
