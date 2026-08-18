import client from '@/services/api';
import { emsHttp, toEmsApiError, toPaginated, type EmsApiError } from '@/services/ems/emsClient';
import type { EmsPaginated, EmsSuccessEnvelope } from '@/types/ems';
import type {
  AttendeeListParams,
  EmsAttendee,
  EmsCheckInResult,
  EmsImportMapping,
  EmsImportPreview,
  EmsOperationsSummary,
} from '@/types/ems/operations';

const EMS_PREFIX = '/ems';

async function postForm<T>(url: string, form: FormData): Promise<T> {
  try {
    const response = await client.post<EmsSuccessEnvelope<T>>(`${EMS_PREFIX}${url}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (!response.data?.success) {
      throw toEmsApiError({ response });
    }
    return response.data.data;
  } catch (error) {
    throw toEmsApiError(error);
  }
}

export const operationsService = {
  getSummary(eventUuid: string): Promise<EmsOperationsSummary> {
    return emsHttp.get(`/events/${eventUuid}/operations`);
  },

  async listAttendees(
    eventUuid: string,
    params: AttendeeListParams = {}
  ): Promise<EmsPaginated<EmsAttendee>> {
    const envelope = await emsHttp.getWithMeta<EmsAttendee[]>(`/events/${eventUuid}/attendees`, params as Record<string, unknown>);
    return toPaginated(envelope.data, envelope.meta);
  },

  validateTicket(eventUuid: string, code: string): Promise<EmsCheckInResult> {
    return emsHttp.post(`/events/${eventUuid}/validate-ticket`, { code });
  },

  async checkIn(
    eventUuid: string,
    payload: { code: string; method?: string; device?: string; override?: boolean }
  ): Promise<EmsCheckInResult> {
    try {
      const response = await client.post<EmsSuccessEnvelope<EmsCheckInResult>>(
        `${EMS_PREFIX}/events/${eventUuid}/check-in`,
        payload
      );
      if (!response.data?.success) {
        throw toEmsApiError({ response });
      }
      return response.data.data;
    } catch (error) {
      const response = (error as { response?: { data?: { data?: EmsCheckInResult; message?: string; errors?: Record<string, string[]> }; status?: number } })?.response;
      if (response?.data?.data && typeof response.data.data === 'object') {
        const result = response.data.data;
        const err = toEmsApiError(error) as EmsApiError & { checkInResult?: EmsCheckInResult };
        err.checkInResult = result;
        throw err;
      }
      throw toEmsApiError(error);
    }
  },

  manualCheckIn(
    eventUuid: string,
    payload: { registration_uuid?: string; ticket_code?: string; device?: string }
  ): Promise<EmsCheckInResult> {
    return emsHttp.post(`/events/${eventUuid}/manual-check-in`, payload);
  },

  walkIn(
    eventUuid: string,
    payload: {
      attendee_name: string;
      attendee_email?: string;
      attendee_phone?: string;
      ticket_type_id: string;
      check_in?: boolean;
      is_member?: boolean;
    }
  ): Promise<{
    registration: { uuid: string };
    check_in: unknown;
    checkout_url: string | null;
  }> {
    return emsHttp.post(`/events/${eventUuid}/walk-in`, payload);
  },

  terminalCheckout(
    eventUuid: string,
    payload: {
      attendee_name: string;
      attendee_email?: string;
      attendee_phone?: string;
      ticket_type_id: string;
      quantity?: number;
      device_id?: string;
    }
  ) {
    return emsHttp.post(`/events/${eventUuid}/terminal-checkout`, payload);
  },

  refundPayment(paymentUuid: string, payload: { amount?: number; reason?: string } = {}) {
    return emsHttp.post(`/payments/${encodeURIComponent(paymentUuid)}/refund`, payload);
  },

  undoCheckIn(
    eventUuid: string,
    payload: { check_in_uuid?: string; ticket_code?: string; reason: string }
  ): Promise<{ ok: boolean; code: string; message: string }> {
    return emsHttp.post(`/events/${eventUuid}/undo-check-in`, payload);
  },

  previewImport(
    eventUuid: string,
    file: File,
    mapping: Record<string, string | null>
  ): Promise<EmsImportPreview> {
    const form = new FormData();
    form.append('file', file);
    form.append('column_mapping', JSON.stringify(mapping));
    return postForm(`/events/${eventUuid}/import/preview`, form);
  },

  commitImport(eventUuid: string, importUuid: string) {
    return emsHttp.post(`/events/${eventUuid}/import`, { import_uuid: importUuid });
  },

  listMappings(eventUuid: string): Promise<EmsImportMapping[]> {
    return emsHttp.get(`/events/${eventUuid}/import/mappings`);
  },

  saveMapping(eventUuid: string, name: string, mapping: Record<string, string | null>) {
    return emsHttp.post(`/events/${eventUuid}/import/mappings`, { name, mapping });
  },
};

/** Parse check-in error responses that include a structured `data` payload. */
export function checkInErrorPayload(error: unknown): EmsCheckInResult | null {
  const withResult = error as EmsApiError & { checkInResult?: EmsCheckInResult };
  if (withResult?.checkInResult) {
    return withResult.checkInResult;
  }

  const response = (error as { response?: { data?: { data?: EmsCheckInResult } } })?.response?.data;
  if (response?.data && typeof response.data === 'object' && 'code' in response.data) {
    return response.data;
  }

  const apiError = error as EmsApiError;
  if (apiError?.message) {
    return {
      ok: false,
      code: (apiError.errors?.code?.[0] as string) || 'error',
      message: apiError.message,
      previous_check_in: null,
    };
  }

  return null;
}
