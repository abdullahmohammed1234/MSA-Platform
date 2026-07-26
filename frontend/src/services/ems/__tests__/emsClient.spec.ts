import { describe, it, expect } from 'vitest';
import { EmsApiError, toEmsApiError } from '../emsClient';

/**
 * The EMS UI branches on EmsApiError, never on axios internals, so this is the
 * contract that keeps every view's error handling consistent.
 */
describe('toEmsApiError', () => {
  const axiosLike = (status: number, data: unknown) => ({ response: { status, data } });

  it('keeps field errors from a 422 for inline display', () => {
    const error = toEmsApiError(
      axiosLike(422, {
        success: false,
        message: 'Validation failed.',
        errors: { name: ['The event name field is required.'] },
      })
    );

    expect(error.isValidation).toBe(true);
    expect(error.message).toBe('Validation failed.');
    expect(error.fieldError('name')).toBe('The event name field is required.');
    expect(error.fieldError('slug')).toBeUndefined();
  });

  it('flags an illegal lifecycle transition as a conflict', () => {
    const error = toEmsApiError(
      axiosLike(409, {
        success: false,
        message: 'A draft event cannot be completed.',
      })
    );

    expect(error.isConflict).toBe(true);
    expect(error.isValidation).toBe(false);
    expect(error.message).toBe('A draft event cannot be completed.');
  });

  it.each([
    [401, 'isUnauthenticated'],
    [403, 'isForbidden'],
    [404, 'isNotFound'],
    [429, 'isRateLimited'],
    [500, 'isServer'],
  ] as const)('maps HTTP %i onto its flag', (status, flag) => {
    const error = toEmsApiError(axiosLike(status, {}));

    expect(error[flag]).toBe(true);
    expect(error.status).toBe(status);
  });

  it('falls back to a safe message when the body carries none', () => {
    const error = toEmsApiError(axiosLike(500, {}));

    expect(error.message).toBe('An unexpected error occurred. Please try again.');
  });

  it('reports a request that never reached the API as a network failure', () => {
    const error = toEmsApiError(new Error('Network Error'));

    expect(error.isNetwork).toBe(true);
    expect(error.status).toBeNull();
  });

  it('passes an EmsApiError through unchanged', () => {
    const original = new EmsApiError('Already normalised.', 403);

    expect(toEmsApiError(original)).toBe(original);
  });
});
