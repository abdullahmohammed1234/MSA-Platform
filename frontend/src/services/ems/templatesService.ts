import { emsHttp } from './emsClient';
import type { EventTemplate, EventTemplatePayload } from '@/types/ems';

export const templatesService = {
  /** GET /ems/templates */
  list(): Promise<EventTemplate[]> {
    return emsHttp.get<EventTemplate[]>('/event-templates');
  },

  /** GET /ems/templates/{uuid} */
  show(uuid: string): Promise<EventTemplate> {
    return emsHttp.get<EventTemplate>(`/event-templates/${uuid}`);
  },

  /** POST /ems/templates */
  create(payload: EventTemplatePayload): Promise<EventTemplate> {
    return emsHttp.post<EventTemplate>('/event-templates', payload);
  },

  /** PUT /ems/templates/{uuid} */
  update(uuid: string, payload: Partial<EventTemplatePayload>): Promise<EventTemplate> {
    return emsHttp.put<EventTemplate>(`/event-templates/${uuid}`, payload);
  },

  /** DELETE /ems/templates/{uuid} */
  async remove(uuid: string): Promise<void> {
    await emsHttp.delete(`/event-templates/${uuid}`);
  },
};
