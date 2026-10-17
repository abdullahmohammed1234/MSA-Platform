import apiClient from './api';
import { getApiBaseUrl } from '@/config/api';
import type {
  PublicEvent,
  PublicEventDetail,
  PublicCategory,
  RegisterPayload,
  Registration,
  CalendarLinks,
  EventFilterParams,
  EmsEnvelope,
} from '@/types/ems';

export const emsService = {
  /**
   * Fetch public events catalog with optional search and filters.
   */
  async getEvents(params: EventFilterParams = {}): Promise<EmsEnvelope<PublicEvent[]>> {
    const response = await apiClient.get<EmsEnvelope<PublicEvent[]>>('/ems/public/events', {
      params,
    });
    return response.data;
  },

  /**
   * Fetch full event details by slug.
   */
  async getEventBySlug(slug: string): Promise<EmsEnvelope<PublicEventDetail>> {
    const response = await apiClient.get<EmsEnvelope<PublicEventDetail>>(`/ems/public/events/${encodeURIComponent(slug)}`);
    return response.data;
  },

  /**
   * Fetch event categories list.
   */
  async getCategories(): Promise<EmsEnvelope<PublicCategory[]>> {
    const response = await apiClient.get<EmsEnvelope<PublicCategory[]>>('/ems/public/categories');
    return response.data;
  },

  /**
   * Submit registration for a free event.
   */
  async registerForEvent(slug: string, payload: RegisterPayload): Promise<EmsEnvelope<Registration>> {
    const response = await apiClient.post<EmsEnvelope<Registration>>(
      `/ems/public/events/${encodeURIComponent(slug)}/register`,
      payload
    );
    return response.data;
  },

  /**
   * Fetch authenticated user's tickets and registrations.
   */
  async getMyTickets(activeOnly = false): Promise<EmsEnvelope<Registration[]>> {
    const response = await apiClient.get<EmsEnvelope<Registration[]>>('/ems/public/my-tickets', {
      params: activeOnly ? { active_only: true } : {},
    });
    return response.data;
  },

  /**
   * Cancel a confirmed registration using registration UUID.
   */
  async cancelRegistration(registrationUuid: string): Promise<EmsEnvelope<Registration>> {
    const response = await apiClient.post<EmsEnvelope<Registration>>(
      `/ems/public/registrations/${encodeURIComponent(registrationUuid)}/cancel`
    );
    return response.data;
  },

  /**
   * Retrieve external calendar compose links (Google, Outlook, Yahoo, ICS).
   */
  async getCalendarLinks(slug: string): Promise<EmsEnvelope<CalendarLinks>> {
    const response = await apiClient.get<EmsEnvelope<CalendarLinks>>(
      `/ems/public/events/${encodeURIComponent(slug)}/calendar`
    );
    return response.data;
  },

  /**
   * Construct absolute URL for direct RFC 5545 iCalendar stream download.
   */
  getIcsDownloadUrl(slug: string): string {
    return `${getApiBaseUrl()}/ems/public/events/${encodeURIComponent(slug)}/ics`;
  },
};

export default emsService;
