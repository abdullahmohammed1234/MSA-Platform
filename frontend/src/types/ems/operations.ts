export type CheckInMethod = 'manual' | 'qr_scan' | 'walk_in' | 'import';

export type CheckInResultCode =
  | 'valid'
  | 'checked_in'
  | 'undone'
  | 'invalid_qr'
  | 'ticket_not_found'
  | 'wrong_event'
  | 'cancelled_registration'
  | 'refunded_ticket'
  | 'payment_required'
  | 'already_checked_in'
  | 'inactive_ticket'
  | 'waitlisted';

export interface EmsAttendee {
  uuid: string;
  reference: string;
  attendee_name: string;
  attendee_email: string;
  attendee_phone: string | null;
  ticket_type: { uuid: string; name: string } | null;
  ticket_code: string | null;
  ticket_uuid: string | null;
  qr_payload: string | null;
  registration_status: string;
  registration_status_label: string;
  payment_status: string;
  payment_status_label: string;
  check_in_status: string;
  check_in_status_label: string;
  check_in_at: string | null;
  checked_in_by: string | null;
  registration_source: string;
  is_member: boolean;
  registered_at: string | null;
  quantity: number;
}

export interface EmsCheckIn {
  uuid: string;
  ticket_code?: string | null;
  ticket_uuid?: string | null;
  registration_uuid?: string | null;
  attendee_name?: string | null;
  attendee_email?: string | null;
  method: CheckInMethod;
  method_label: string;
  device?: string | null;
  checked_in_at: string | null;
  staff_name?: string | null;
  notes?: string | null;
}

export interface EmsOperationsSummary {
  event_uuid: string;
  event_name: string;
  event_status: string;
  registered_count: number;
  confirmed_count: number;
  checked_in_count: number;
  remaining_count: number | null;
  capacity: number | null;
  waitlist_count: number;
  walk_in_count: number;
  attendance_percentage: number;
  registration_status_summary: Record<string, number>;
  payment_summary?: {
    paid_amount: number;
    pending_amount: number;
    refunded_amount: number;
    failed_count: number;
    currency: string;
  };
  recent_check_ins: Array<{
    uuid: string;
    attendee_name: string | null;
    ticket_code: string | null;
    method: string;
    method_label: string;
    checked_in_at: string | null;
    staff_name: string | null;
  }>;
}

export interface EmsCheckInResult {
  ok: boolean;
  code: CheckInResultCode | string;
  message: string;
  check_in?: EmsCheckIn | null;
  ticket?: { code: string; uuid: string; status: string } | null;
  registration?: { uuid: string; attendee_name?: string; attendee_email?: string } | null;
  previous_check_in?: {
    checked_in_at?: string;
    staff_name?: string;
    check_in_uuid?: string;
    method?: string;
  } | null;
  checkout_url?: string | null;
}

export interface EmsImportPreview {
  import_uuid: string;
  total: number;
  valid: number;
  invalid: number;
  duplicates: number;
  tickets_to_generate: number;
  valid_rows: Array<Record<string, unknown>>;
  invalid_rows: Array<Record<string, unknown>>;
  duplicate_rows: Array<Record<string, unknown>>;
  headers: string[];
}

export interface EmsImportMapping {
  uuid: string;
  name: string;
  mapping: Record<string, string | null>;
}

export interface AttendeeListParams {
  search?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  ticket_type_id?: string;
  registration_status?: string;
  payment_status?: string;
  check_in_status?: string;
  is_member?: boolean | string;
  source?: string;
}

/** Extract a ticket code from a raw QR payload or URL. */
export function extractTicketCode(raw: string): string {
  const trimmed = raw.trim();
  if (!trimmed) return '';

  try {
    if (trimmed.includes('/')) {
      const url = new URL(trimmed.includes('://') ? trimmed : `https://local${trimmed.startsWith('/') ? '' : '/'}${trimmed}`);
      const parts = url.pathname.split('/').filter(Boolean);
      const last = parts[parts.length - 1] ?? trimmed;
      return last.split(/[?#]/)[0]?.toUpperCase() ?? '';
    }
  } catch {
    // fall through
  }

  const segments = trimmed.split('/').filter(Boolean);
  const last = segments[segments.length - 1] ?? trimmed;
  return last.split(/[?#]/)[0]?.toUpperCase() ?? '';
}
