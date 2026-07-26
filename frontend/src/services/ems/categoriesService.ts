import { emsHttp } from './emsClient';
import type { EventCategory, EventCategoryFilters, EventCategoryPayload } from '@/types/ems';

export const categoriesService = {
  /**
   * GET /ems/event-categories
   *
   * Unpaginated by design: the taxonomy is small and every event form needs
   * the whole list to populate its category select.
   */
  list(filters: EventCategoryFilters = {}): Promise<EventCategory[]> {
    const params = Object.fromEntries(
      Object.entries(filters).filter(([, value]) => value !== undefined && value !== '')
    );

    return emsHttp.get<EventCategory[]>('/event-categories', params);
  },

  /** GET /ems/event-categories/{uuid} */
  show(uuid: string): Promise<EventCategory> {
    return emsHttp.get<EventCategory>(`/event-categories/${uuid}`);
  },

  /** POST /ems/event-categories */
  create(payload: EventCategoryPayload): Promise<EventCategory> {
    return emsHttp.post<EventCategory>('/event-categories', payload);
  },

  /** PUT /ems/event-categories/{uuid} */
  update(uuid: string, payload: Partial<EventCategoryPayload>): Promise<EventCategory> {
    return emsHttp.put<EventCategory>(`/event-categories/${uuid}`, payload);
  },

  /** DELETE /ems/event-categories/{uuid} — answers 409 when events still use it. */
  async remove(uuid: string): Promise<void> {
    await emsHttp.delete(`/event-categories/${uuid}`);
  },
};
