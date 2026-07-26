import { emsHttp, toPaginated } from './emsClient';
import type {
  EmsPaginated,
  Event,
  EventLifecycleGraph,
  EventListFilters,
  EventPayload,
  EventTransitionAction,
} from '@/types/ems';

export const eventsService = {
  /**
   * GET /ems/events
   *
   * Blank filters are stripped so the backend only validates what the caller
   * actually chose.
   */
  async list(filters: EventListFilters = {}): Promise<EmsPaginated<Event>> {
    const { data, meta } = await emsHttp.getWithMeta<Event[]>('/events', prune(filters));

    return toPaginated(data, meta);
  },

  /** GET /ems/events/{uuid} */
  show(uuid: string): Promise<Event> {
    return emsHttp.get<Event>(`/events/${uuid}`);
  },

  /** POST /ems/events */
  create(payload: EventPayload): Promise<Event> {
    return emsHttp.post<Event>('/events', payload);
  },

  /** PUT /ems/events/{uuid} */
  update(uuid: string, payload: Partial<EventPayload>): Promise<Event> {
    return emsHttp.put<Event>(`/events/${uuid}`, payload);
  },

  /** DELETE /ems/events/{uuid} */
  async remove(uuid: string): Promise<void> {
    await emsHttp.delete(`/events/${uuid}`);
  },

  /**
   * POST /ems/events/{uuid}/transitions
   *
   * Status is never sent as a field on the event itself: it is the outcome of
   * a named transition that the server validates against the state machine.
   */
  transition(uuid: string, action: EventTransitionAction): Promise<Event> {
    return emsHttp.post<Event>(`/events/${uuid}/transitions`, { action });
  },

  /** GET /ems/events/lifecycle — the state graph, so the UI never hard-codes it. */
  lifecycle(): Promise<EventLifecycleGraph> {
    return emsHttp.get<EventLifecycleGraph>('/events/lifecycle');
  },
};

function prune(filters: EventListFilters): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && value !== '')
  );
}
