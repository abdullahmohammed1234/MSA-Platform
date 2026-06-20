import axios from 'axios';

const client = axios.create({
  baseURL: (import.meta.env.VITE_API_URL as string) || 'http://localhost:8000/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request Interceptor: Attach Auth Token
client.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response Interceptor: Handle errors and expired session
client.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    const status = error.response ? error.response.status : null;

    if (error.code === 'ECONNABORTED') {
      error.message = 'The server took too long to respond. Make sure the backend is running on port 8000.';
    } else if (!error.response) {
      error.message = 'Unable to reach the API server. Start the backend with: php artisan serve';
    }

    if (status === 401) {
      // Clear local authentication details
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      localStorage.removeItem('user_role');

      // Dispatch global event for Pinia store to react
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));
      
      // Redirect to login if not already there
      if (window.location.pathname !== '/login') {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
      }
    }

    return Promise.reject(error);
  }
);

export default client;
