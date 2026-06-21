/** @typedef {import('vite/types/importMeta.d.ts').ImportMetaEnv} ImportMetaEnv */

const PRODUCTION_API_URL = 'https://api.sfumsa.ca/api/v1';
const DEVELOPMENT_API_URL = 'http://localhost:8000/api/v1';

/**
 * Resolve the full API base URL (includes /api/v1).
 * @returns {string}
 */
export function getApiBaseUrl() {
  const configured = import.meta.env.VITE_API_URL;

  if (typeof configured === 'string' && configured.trim()) {
    return configured.trim().replace(/\/$/, '');
  }

  return import.meta.env.PROD ? PRODUCTION_API_URL : DEVELOPMENT_API_URL;
}

/**
 * API origin without the /api/v1 suffix (used for storage/media URLs).
 * @returns {string}
 */
export function getApiOrigin() {
  return getApiBaseUrl().replace(/\/api\/v1\/?$/, '');
}

export { PRODUCTION_API_URL, DEVELOPMENT_API_URL };
