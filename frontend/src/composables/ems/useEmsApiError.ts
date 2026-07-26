import { ref } from 'vue';
import { useToastStore } from '@/components/feedback/toast';
import { EmsApiError, toEmsApiError } from '@/services/ems';

interface HandleOptions {
  /** Shown instead of the server message for non-validation failures. */
  fallbackMessage?: string;
  /** Suppress the toast, e.g. when the caller renders the error inline. */
  silent?: boolean;
}

/**
 * The single place EMS views deal with a failed request.
 *
 * Validation failures are kept as a field map for inline display and are not
 * toasted — the offending inputs already say what is wrong. Everything else
 * gets one toast with a message the backend has already made safe to show.
 */
export function useEmsApiError() {
  const toast = useToastStore();

  /** Field-keyed messages from the last 422. */
  const fieldErrors = ref<Record<string, string[]>>({});

  /** The last non-validation message, for inline error panels. */
  const generalError = ref<string | null>(null);

  const clear = () => {
    fieldErrors.value = {};
    generalError.value = null;
  };

  const fieldError = (field: string): string | undefined => fieldErrors.value[field]?.[0];

  const handle = (caught: unknown, options: HandleOptions = {}): EmsApiError => {
    const error = toEmsApiError(caught);

    clear();

    if (error.isValidation) {
      fieldErrors.value = error.errors;

      // A 422 can carry a form-level message with no field attached.
      if (Object.keys(error.errors).length === 0) {
        generalError.value = error.message;

        if (!options.silent) {
          toast.error(error.message);
        }
      }

      return error;
    }

    const message = options.fallbackMessage ?? error.message;
    generalError.value = message;

    // A 401 is already being handled globally by the axios interceptor, which
    // clears the session and redirects; a toast would flash and disappear.
    if (!options.silent && !error.isUnauthenticated) {
      toast.error(message);
    }

    return error;
  };

  /**
   * Run an async action, routing any failure through `handle`. Resolves to
   * null on failure so callers can branch without a try/catch.
   */
  const attempt = async <T>(action: () => Promise<T>, options: HandleOptions = {}): Promise<T | null> => {
    try {
      clear();
      return await action();
    } catch (caught) {
      handle(caught, options);
      return null;
    }
  };

  return {
    fieldErrors,
    generalError,
    fieldError,
    clear,
    handle,
    attempt,
  };
}
