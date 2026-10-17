/**
 * Shared password-reset request copy.
 * Success text must be identical for known and unknown addresses.
 */
export const GENERIC_PASSWORD_RESET_MESSAGE =
  'If an account matches that email address, password reset instructions will be sent.';

const EXISTENCE_LEAK = /could not find|couldn't find|does not exist|doesn't exist|no account|not registered|selected email is invalid/i;

export interface PasswordResetRequestResult {
  ok: boolean;
  status?: number;
  message?: string;
  emailErrors?: string[];
}

export interface PasswordResetNotice {
  kind: 'success' | 'error';
  text: string;
}

export function interpretPasswordResetRequest(result: PasswordResetRequestResult): PasswordResetNotice {
  if (result.ok) {
    return { kind: 'success', text: GENERIC_PASSWORD_RESET_MESSAGE };
  }

  const status = result.status ?? 0;
  const emailErrors = result.emailErrors ?? [];

  if (status === 422 && emailErrors.length > 0) {
    if (emailErrors.some((error) => EXISTENCE_LEAK.test(error))) {
      return { kind: 'success', text: GENERIC_PASSWORD_RESET_MESSAGE };
    }
    return { kind: 'error', text: emailErrors[0] };
  }

  if (status === 429) {
    return {
      kind: 'error',
      text: result.message || 'Too many attempts. Please try again later.',
    };
  }

  return {
    kind: 'error',
    text: 'Unable to process reset request. Please try again shortly.',
  };
}
