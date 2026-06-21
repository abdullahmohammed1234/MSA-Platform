/** @typedef {import('vite/types/importMeta.d.ts').ImportMetaEnv} ImportMetaEnv */

export const PRODUCTION_APP_URL = 'https://sfumsa.ca';
export const PRODUCTION_API_URL = 'https://api.sfumsa.ca/api/v1';
export const DEVELOPMENT_APP_URL = 'http://localhost:5173';
export const DEVELOPMENT_API_URL = 'http://localhost:8000/api/v1';

/**
 * Resolve the public frontend base URL (no trailing slash).
 * @returns {string}
 */
export function getAppBaseUrl() {
  const configured = import.meta.env.VITE_APP_URL;

  if (typeof configured === 'string' && configured.trim()) {
    return configured.trim().replace(/\/$/, '');
  }

  return import.meta.env.PROD ? PRODUCTION_APP_URL : DEVELOPMENT_APP_URL;
}

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

/**
 * Rewrite legacy localhost storage URLs saved during local development.
 * @param {string} url
 * @returns {string}
 */
export function rewriteLocalhostUrl(url) {
  const apiOrigin = getApiOrigin();
  return url.replace(/^https?:\/\/localhost(?::\d+)?/i, apiOrigin);
}
