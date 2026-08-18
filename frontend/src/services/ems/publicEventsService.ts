import { emsHttp, toPaginated } from '@/services/ems/emsClient';
import type { EmsPaginated } from '@/types/ems';
import type {
  CalendarFilters,
  PublicCalendarEvent,
  PublicCategory,
  PublicEvent,
  PublicEventDetail,
  PublicEventFilters,
  PublicRegistration,
  PublicTicket,
  RegisterForEventPayload,
} from '@/types/ems/public';
import type { CheckoutResult, PublicTicketType, WaitlistEntry } from '@/types/ems/ticketing';

/**
 * Unauthenticated EMS public API client (Phase 2 / 3).
 * Paths are relative to /api/v1/ems via emsHttp.
 */
export const publicEventsService = {
  async listEvents(filters: PublicEventFilters = {}): Promise<EmsPaginated<PublicEvent>> {
    const params = compact(filters);
    const envelope = await emsHttp.getWithMeta<PublicEvent[]>('/public/events', params);
    return toPaginated(envelope.data, envelope.meta);
  },

  async getEvent(slug: string): Promise<PublicEventDetail> {
    return emsHttp.get<PublicEventDetail>(`/public/events/${encodeURIComponent(slug)}`);
  },

  async listCategories(): Promise<PublicCategory[]> {
    return emsHttp.get<PublicCategory[]>('/public/categories');
  },

  async calendar(filters: CalendarFilters = {}): Promise<PublicCalendarEvent[]> {
    return emsHttp.get<PublicCalendarEvent[]>('/public/events/calendar', compact(filters));
  },

  async listTicketTypes(slug: string): Promise<PublicTicketType[]> {
    return emsHttp.get(`/public/events/${encodeURIComponent(slug)}/tickets`);
  },

  async register(slug: string, payload: RegisterForEventPayload): Promise<PublicRegistration> {
    return emsHttp.post<PublicRegistration>(
      `/public/events/${encodeURIComponent(slug)}/register`,
      payload
    );
  },

  async checkout(slug: string, payload: RegisterForEventPayload & { ticket_type_id: string }): Promise<CheckoutResult> {
    return emsHttp.post<CheckoutResult>(
      `/public/events/${encodeURIComponent(slug)}/checkout`,
      payload
    );
  },

  async resumeCheckout(
    slug: string,
    payload: { email: string; order_uuid?: string; ticket_type_id?: string; quantity?: number; promo_code?: string | null; first_name?: string; last_name?: string; phone?: string | null }
  ): Promise<CheckoutResult> {
    return emsHttp.post<CheckoutResult>(
      `/public/events/${encodeURIComponent(slug)}/checkout/resume`,
      payload
    );
  },

  async cancelCheckout(slug: string, email: string, orderUuid: string): Promise<void> {
    await emsHttp.post(
      `/public/events/${encodeURIComponent(slug)}/checkout/cancel`,
      { email, order_uuid: orderUuid }
    );
  },

  async joinWaitlist(slug: string, payload: RegisterForEventPayload & { ticket_type_id?: string }): Promise<WaitlistEntry> {
    return emsHttp.post(
      `/public/events/${encodeURIComponent(slug)}/waitlist`,
      payload
    );
  },

  async leaveWaitlist(slug: string, entryUuid: string, email?: string): Promise<void> {
    const params = email ? `?email=${encodeURIComponent(email)}` : '';
    await emsHttp.delete(
      `/public/events/${encodeURIComponent(slug)}/waitlist/${encodeURIComponent(entryUuid)}${params}`
    );
  },

  async getTicket(code: string): Promise<PublicTicket> {
    return emsHttp.get<PublicTicket>(`/public/tickets/${encodeURIComponent(code)}`);
  },

  async getMyTickets(): Promise<PublicRegistration[]> {
    return emsHttp.get<PublicRegistration[]>('/public/my-tickets');
  },

  async cancelRegistration(uuid: string): Promise<PublicRegistration> {
    return emsHttp.post<PublicRegistration>(`/public/registrations/${encodeURIComponent(uuid)}/cancel`);
  },

  async validatePromoCode(payload: {
    code: string;
    event_uuid: string;
    ticket_type_uuid?: string | null;
    email?: string | null;
    amount?: number | null;
  }): Promise<{
    valid: boolean;
    code: string;
    discount_type: string;
    discount_value: number;
    discount_amount: number;
  }> {
    return emsHttp.post('/public/promo-codes/validate', payload);
  },

  async validateTicket(code: string): Promise<PublicTicket & { valid: boolean }> {
    return emsHttp.get(`/public/tickets/validate/${encodeURIComponent(code)}`);
  },

  ticketQrUrl(code: string): string {
    const base = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/$/, '')
      || '/api/v1';
    return `${base}/ems/public/tickets/${encodeURIComponent(code)}/qr`;
  },
};

function compact<T extends object>(input: T): Record<string, unknown> {
  const out: Record<string, unknown> = {};

  for (const [key, value] of Object.entries(input)) {
    if (value === undefined || value === null || value === '') continue;
    // Query strings: send 1/0 so Laravel's boolean rule accepts the params.
    out[key] = typeof value === 'boolean' ? (value ? 1 : 0) : value;
  }

  return out;
}

export default publicEventsService;
