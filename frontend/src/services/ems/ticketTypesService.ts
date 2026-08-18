import { emsHttp } from '@/services/ems/emsClient';
import type {
  EventPaymentSummary,
  TicketType,
  TicketTypePayload,
} from '@/types/ems/ticketing';

export const ticketTypesService = {
  list(eventUuid: string): Promise<TicketType[]> {
    return emsHttp.get(`/events/${encodeURIComponent(eventUuid)}/tickets`);
  },

  create(eventUuid: string, payload: TicketTypePayload): Promise<TicketType> {
    return emsHttp.post(`/events/${encodeURIComponent(eventUuid)}/tickets`, payload);
  },

  update(eventUuid: string, ticketUuid: string, payload: Partial<TicketTypePayload>): Promise<TicketType> {
    return emsHttp.put(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}`,
      payload
    );
  },

  disable(eventUuid: string, ticketUuid: string): Promise<TicketType> {
    return emsHttp.post(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}/disable`
    );
  },

  duplicate(eventUuid: string, ticketUuid: string): Promise<TicketType> {
    return emsHttp.post(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}/duplicate`
    );
  },

  async remove(eventUuid: string, ticketUuid: string): Promise<void> {
    await emsHttp.delete(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}`
    );
  },

  reorder(eventUuid: string, orderedUuids: string[]): Promise<TicketType[]> {
    return emsHttp.post(`/events/${encodeURIComponent(eventUuid)}/tickets/reorder`, {
      ordered_uuids: orderedUuids,
    });
  },

  paymentSummary(eventUuid: string): Promise<EventPaymentSummary> {
    return emsHttp.get(`/events/${encodeURIComponent(eventUuid)}/payment-summary`);
  },

  syncToSquare(eventUuid: string, ticketUuid: string): Promise<TicketType> {
    return emsHttp.post(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}/sync-square`
    );
  },

  refreshFromSquare(eventUuid: string, ticketUuid: string): Promise<TicketType> {
    return emsHttp.post(
      `/events/${encodeURIComponent(eventUuid)}/tickets/${encodeURIComponent(ticketUuid)}/refresh-square`
    );
  },
};

export default ticketTypesService;
