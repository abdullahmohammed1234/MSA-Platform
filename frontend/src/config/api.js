/** @typedef {import('vite/types/importMeta.d.ts').ImportMetaEnv} ImportMetaEnv */

export const PRODUCTION_APP_URL = 'https://sfumsa.ca';
export const PRODUCTION_API_URL = 'https://api.sfumsa.ca/api/v1';
export const DEVELOPMENT_APP_URL = 'http://localhost:5173';
export const DEVELOPMENT_API_URL = 'http://localhost:8000/api/v1';
export const PRODUCTION_PMS_URL = 'https://projects.sfumsa.ca';
export const DEVELOPMENT_PMS_URL = 'http://localhost:5175';

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
 * Resolve the PMS application base URL (no trailing slash).
 * @returns {string}
 */
export function getPmsBaseUrl() {
  const configured = import.meta.env.VITE_PMS_URL;

  if (typeof configured === 'string' && configured.trim()) {
    return configured.trim().replace(/\/$/, '');
  }

  return import.meta.env.PROD ? PRODUCTION_PMS_URL : DEVELOPMENT_PMS_URL;
}

/**
 * Resolve the PMS application launch URL using secure fragment ticket handover (SEC-11-01).
 * Never exposes long-lived Sanctum tokens in URL query strings.
 * @param {string | null} [ticket]
 * @returns {string}
 */
export function getPmsLaunchUrl(ticket = null) {
  const base = getPmsBaseUrl();
  if (ticket && typeof ticket === 'string' && ticket.trim()) {
    return `${base}/#sso_ticket=${encodeURIComponent(ticket.trim())}`;
  }
  return base;
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
