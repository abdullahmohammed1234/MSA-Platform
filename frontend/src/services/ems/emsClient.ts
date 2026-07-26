import type { AxiosRequestConfig } from 'axios';
import client from '@/services/api';
import type { EmsMeta, EmsPaginated, EmsSuccessEnvelope } from '@/types/ems';

/**
 * The EMS transport layer.
 *
 * Every EMS request goes through here, so the shared axios instance keeps
 * owning the bearer token, the base URL and the global 401 handling, while
 * this module owns the two things that are specific to the EMS API: the
 * `{ success, message, data, meta }` envelope and a single error type the UI
 * can rely on.
 */

/** Path segment appended to the platform's /api/v1 base URL. */
const EMS_PREFIX = '/ems';

/**
 * The one error shape the EMS UI ever has to handle.
 *
 * Views should not inspect axios internals or HTTP codes directly; they check
 * the flags below and show `message`, which is always safe to display because
 * the backend never puts implementation detail in it.
 */
export class EmsApiError extends Error {
  readonly status: number | null;

  /** Field-keyed messages from a 422, ready to bind to form inputs. */
  readonly errors: Record<string, string[]>;

  constructor(message: string, status: number | null = null, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'EmsApiError';
    this.status = status;
    this.errors = errors;
  }

  get isValidation(): boolean {
    return this.status === 422;
  }

  get isUnauthenticated(): boolean {
    return this.status === 401;
  }

  get isForbidden(): boolean {
    return this.status === 403;
  }

  get isNotFound(): boolean {
    return this.status === 404;
  }

  /** A business-rule refusal, e.g. an illegal transition or a category in use. */
  get isConflict(): boolean {
    return this.status === 409;
  }

  get isRateLimited(): boolean {
    return this.status === 429;
  }

  get isServer(): boolean {
    return this.status !== null && this.status >= 500;
  }

  /** True when the request never reached the API. */
  get isNetwork(): boolean {
    return this.status === null;
  }

  /** The first message recorded against a field, for inline form display. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0];
  }
}

/** Normalise anything thrown by axios into an EmsApiError. */
export function toEmsApiError(error: unknown): EmsApiError {
  if (error instanceof EmsApiError) {
    return error;
  }

  const response = (error as { response?: { status?: number; data?: unknown } })?.response;

  if (!response) {
    const message = (error as Error)?.message
      || 'Unable to reach the event management API. Check your connection and try again.';

    return new EmsApiError(message, null);
  }

  const body = (response.data ?? {}) as { message?: string; errors?: Record<string, string[]> };
  const status = response.status ?? null;

  return new EmsApiError(
    body.message || defaultMessageFor(status),
    status,
    body.errors ?? {}
  );
}

function defaultMessageFor(status: number | null): string {
  switch (status) {
    case 401:
      return 'Your session has expired. Please sign in again.';
    case 403:
      return 'You do not have permission to perform this action.';
    case 404:
      return 'The requested resource was not found.';
    case 409:
      return 'That action is not allowed in the current state.';
    case 422:
      return 'Validation failed.';
    case 429:
      return 'Too many requests. Please slow down and try again shortly.';
    default:
      return 'An unexpected error occurred. Please try again.';
  }
}

async function request<T>(config: AxiosRequestConfig): Promise<EmsSuccessEnvelope<T>> {
  try {
    const response = await client.request<EmsSuccessEnvelope<T>>({
      ...config,
      url: `${EMS_PREFIX}${config.url ?? ''}`,
    });

    const body = response.data;

    // A 2xx that is not a success envelope means the request was intercepted
    // by something other than the EMS API — treat it as a failure rather than
    // handing malformed data to a view.
    if (!body || body.success !== true) {
      throw new EmsApiError(
        (body as unknown as { message?: string })?.message || 'The API returned an unexpected response.',
        response.status
      );
    }

    return body;
  } catch (error) {
    throw toEmsApiError(error);
  }
}

/** Unwrap an envelope to its `data`, discarding the metadata. */
async function data<T>(config: AxiosRequestConfig): Promise<T> {
  return (await request<T>(config)).data;
}

export const emsHttp = {
  /** GET returning only the payload. */
  get: <T>(url: string, params?: Record<string, unknown>) => data<T>({ method: 'get', url, params }),

  /** GET returning the payload and the metadata, for paginated endpoints. */
  getWithMeta: <T>(url: string, params?: Record<string, unknown>) =>
    request<T>({ method: 'get', url, params }),

  post: <T>(url: string, payload?: unknown) => data<T>({ method: 'post', url, data: payload }),

  put: <T>(url: string, payload?: unknown) => data<T>({ method: 'put', url, data: payload }),

  patch: <T>(url: string, payload?: unknown) => data<T>({ method: 'patch', url, data: payload }),

  delete: (url: string) => data<null>({ method: 'delete', url }),
};

/** Fall back to a single-page window when an endpoint omits pagination meta. */
export function toPaginated<T>(items: T[], meta: EmsMeta): EmsPaginated<T> {
  return {
    items,
    pagination: meta.pagination ?? {
      current_page: 1,
      per_page: items.length,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
    },
  };
}
