/**
 * Security and sanitization utilities for external links, QR images, redirect paths, and API origin boundaries.
 */

/**
 * Validates that a QR image source conforms strictly to the verified backend contract.
 * The backend QrCodeGenerator exclusively generates raster PNG data URIs: 'data:image/png;base64,...'.
 *
 * Security requirements:
 * - Strictly enforce PNG format (narrowest allowlist matching backend contract).
 * - Reject JPEG, SVG data URIs, arbitrary data URIs, and external URLs.
 * - Verify valid Base64 payload structure, standard padding, and minimum length.
 * - Verify PNG magic header in Base64 ('iVBOR').
 */
export function isValidQrImageSource(src: string | null | undefined): boolean {
  if (!src || typeof src !== 'string') return false;

  const trimmed = src.trim();

  // Strictly permit raster PNG data URIs matching backend QrCodeGenerator output
  const pngPrefix = 'data:image/png;base64,';
  if (!trimmed.startsWith(pngPrefix)) {
    return false;
  }

  // Extract base64 payload
  const base64Data = trimmed.slice(pngPrefix.length);

  // Require minimum length for a valid QR code bitmap (at least 64 characters)
  if (base64Data.length < 64) {
    return false;
  }

  // Verify PNG file signature in Base64.
  // Standard PNG magic bytes 0x89 0x50 0x4E ('\x89PNG') always encode to 'iVBOR'.
  if (!base64Data.startsWith('iVBOR')) {
    return false;
  }

  // Validate strict Base64 structure:
  // - Chunks of 4 alphanumeric/+/ chars
  // - Proper padding (= or ==) only at the end
  const base64Regex = /^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/;
  return base64Regex.test(base64Data);
}

/**
 * Validates external calendar compose URLs before binding them to href attributes.
 * Prevents arbitrary protocol execution (e.g. javascript:, file:).
 * Enforces HTTPS, recognized calendar provider domains, and disallows embedded credentials.
 */
export function isValidExternalCalendarUrl(url: string | null | undefined): boolean {
  if (!url || typeof url !== 'string') return false;

  const trimmed = url.trim();

  // Must strictly be HTTPS
  if (!trimmed.startsWith('https://')) return false;

  try {
    const parsed = new URL(trimmed);
    if (parsed.protocol !== 'https:') return false;

    // Disallow credentials in calendar URLs (e.g., https://attacker@calendar.google.com)
    if (parsed.username || parsed.password) return false;

    // Disallow non-standard ports
    if (parsed.port && parsed.port !== '443') return false;

    const validCalendarHosts = [
      'calendar.google.com',
      'outlook.live.com',
      'outlook.office.com',
      'outlook.office365.com',
      'calendar.yahoo.com',
    ];

    return validCalendarHosts.some((host) => parsed.hostname === host || parsed.hostname.endsWith(`.${host}`));
  } catch {
    return false;
  }
}

/**
 * Validates that an internal redirection path is safe against Open Redirect attacks.
 * Rejects protocol-relative URLs (//example.com), backslash evasion, control characters,
 * and absolute protocol strings (/https://...).
 */
export function sanitizeRedirectPath(path: string | null | undefined, fallback = '/app/dashboard'): string {
  if (!path || typeof path !== 'string') return fallback;

  const trimmed = path.trim();

  // Must start with '/' but not '//' or '/\'
  if (
    !trimmed.startsWith('/') ||
    trimmed.startsWith('//') ||
    trimmed.startsWith('/\\') ||
    trimmed.includes('\\')
  ) {
    return fallback;
  }

  // Reject ASCII control characters and whitespace
  if (/[\x00-\x1F\x7F\s]/.test(trimmed)) {
    return fallback;
  }

  // Disallow absolute protocol evasion prefixed with slash (e.g. '/https://evil.com' or '/javascript:')
  if (/^\/[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
    return fallback;
  }

  // If redirect target is landing ('/' or '') or login itself, send to fallback dashboard
  if (trimmed === '/' || trimmed === '' || trimmed === '/login') {
    return fallback;
  }

  return trimmed;
}

/**
 * Verifies whether a request destination matches the authorized API origin.
 * Prevents leaking Sanctum Bearer tokens to external or untrusted destinations.
 */
export function isAllowedApiDestination(
  requestUrl: string | undefined | null,
  configuredBaseUrl: string | undefined | null,
  fallbackOrigin = typeof window !== 'undefined' ? window.location.origin : 'https://connect.sfumsa.ca',
): boolean {
  if (!requestUrl || requestUrl.trim() === '') {
    return true;
  }

  const trimmed = requestUrl.trim();

  // Reject protocol-relative URLs or evaluate against origin
  if (trimmed.startsWith('//')) {
    try {
      const targetUrl = new URL(trimmed, fallbackOrigin);
      const resolvedBase = new URL(configuredBaseUrl || fallbackOrigin, fallbackOrigin);
      return targetUrl.origin === resolvedBase.origin;
    } catch {
      return false;
    }
  }

  // Detect URI schemes (e.g., 'https:', 'http:', 'javascript:', 'data:')
  const schemeMatch = trimmed.match(/^([a-zA-Z][a-zA-Z0-9+.-]*):/);
  if (schemeMatch) {
    const scheme = schemeMatch[1].toLowerCase();
    if (scheme !== 'http' && scheme !== 'https') {
      return false; // Non-HTTP schemes cannot be valid API targets
    }

    try {
      const targetUrl = new URL(trimmed);
      const resolvedBase = new URL(configuredBaseUrl || fallbackOrigin, fallbackOrigin);
      return targetUrl.origin === resolvedBase.origin;
    } catch {
      return false;
    }
  }

  // Relative path (e.g., '/auth/me' or 'events') safely resolves against baseURL
  return true;
}
