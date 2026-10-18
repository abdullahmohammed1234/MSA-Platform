/**
 * Validates the API base URL format and security constraints.
 * Throws an explicit error for malformed or insecure URLs to prevent credential leakage.
 */
export function validateApiUrl(url: string, isProduction = false): void {
  if (!url || typeof url !== 'string' || url.trim() === '') {
    throw new Error('API base URL cannot be empty.');
  }

  const trimmed = url.trim();

  // Reject dangerous schemes
  const forbiddenSchemes = ['javascript:', 'data:', 'file:', 'ftp:', 'ws:', 'wss:'];
  const lower = trimmed.toLowerCase();
  for (const scheme of forbiddenSchemes) {
    if (lower.startsWith(scheme)) {
      throw new Error(`Insecure API URL protocol rejected: ${scheme}`);
    }
  }

  // Reject protocol-relative URLs
  if (trimmed.startsWith('//')) {
    throw new Error('Protocol-relative API base URLs are not allowed.');
  }

  // Internal relative path (e.g., '/api/v1') is valid
  if (trimmed.startsWith('/')) {
    return;
  }

  // Absolute URL validation
  try {
    const parsed = new URL(trimmed);
    if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
      throw new Error(`Unsupported API URL protocol: ${parsed.protocol}`);
    }

    // In production, enforce HTTPS (unless targeting localhost for local staging verification)
    if (isProduction && parsed.protocol === 'http:') {
      const isLocalHost = parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1';
      if (!isLocalHost) {
        throw new Error(`Production API base URL must use HTTPS: ${trimmed}`);
      }
    }
  } catch (err: unknown) {
    if (err instanceof Error && err.message.includes('API')) {
      throw err;
    }
    throw new Error(`Malformed API base URL: ${trimmed}`);
  }
}

/**
 * Resolves the API base URL based on environment variables or runtime defaults.
 * Fails clearly if the configured URL is malformed or violates security constraints.
 */
export function getApiBaseUrl(): string {
  const envUrl =
    typeof import.meta !== 'undefined' && import.meta.env
      ? import.meta.env.VITE_API_BASE_URL
      : typeof process !== 'undefined'
        ? process.env?.VITE_API_BASE_URL
        : undefined;
  const isProd =
    typeof import.meta !== 'undefined' && import.meta.env
      ? import.meta.env.PROD
      : typeof process !== 'undefined'
        ? process.env?.NODE_ENV === 'production'
        : false;

  if (envUrl && typeof envUrl === 'string' && envUrl.trim() !== '') {
    const normalized = envUrl.trim().replace(/\/+$/, '');
    validateApiUrl(normalized, isProd);
    return normalized;
  }

  // In local development or node testing, default to Vite dev proxy target
  if (typeof import.meta !== 'undefined' && import.meta.env?.DEV) {
    return '/api/v1';
  }

  // Canonical production endpoint
  return 'https://api.sfumsa.ca/api/v1';
}

/**
 * Returns the API origin without the /api/v1 suffix (used for CSRF cookie bootstrap and storage).
 */
export function getApiOrigin(): string {
  return getApiBaseUrl().replace(/\/api\/v1\/?$/, '');
}
