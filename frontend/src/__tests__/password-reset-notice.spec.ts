import { describe, expect, it } from 'vitest';
import {
  GENERIC_PASSWORD_RESET_MESSAGE,
  interpretPasswordResetRequest,
} from '../utils/passwordResetNotice';

describe('password reset notice', () => {
  it('shows the same generic success for accepted requests', () => {
    const known = interpretPasswordResetRequest({ ok: true, status: 200, message: GENERIC_PASSWORD_RESET_MESSAGE });
    const unknown = interpretPasswordResetRequest({ ok: true, status: 200, message: GENERIC_PASSWORD_RESET_MESSAGE });

    expect(known).toEqual(unknown);
    expect(known).toEqual({ kind: 'success', text: GENERIC_PASSWORD_RESET_MESSAGE });
  });

  it('does not surface an account-existence validation error', () => {
    const notice = interpretPasswordResetRequest({
      ok: false,
      status: 422,
      emailErrors: ['We could not find a user with that email address.'],
    });

    expect(notice.kind).toBe('success');
    expect(notice.text).toBe(GENERIC_PASSWORD_RESET_MESSAGE);
    expect(notice.text).not.toMatch(/find a user|does not exist/i);
  });

  it('shows format validation and rate limiting without a success state', () => {
    expect(interpretPasswordResetRequest({
      ok: false,
      status: 422,
      emailErrors: ['The email field must be a valid email address.'],
    })).toEqual({
      kind: 'error',
      text: 'The email field must be a valid email address.',
    });

    expect(interpretPasswordResetRequest({
      ok: false,
      status: 429,
      message: 'Too many attempts. Please try again later.',
    }).kind).toBe('error');
  });
});
