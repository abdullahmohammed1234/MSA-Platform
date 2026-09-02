import { emsHttp, toPaginated } from '@/services/ems/emsClient';
import type { EmsPaginated } from '@/types/ems';
import type {
  EmsEmailTemplate,
  EmsEventNotification,
  EmsEventReminder,
  EmsNotificationPreferences,
  EmsNotificationSummary,
  ReminderPayload,
} from '@/types/ems/notifications';

export const notificationsService = {
  getSummary(eventUuid: string): Promise<EmsNotificationSummary> {
    return emsHttp.get(`/events/${eventUuid}/notifications/summary`);
  },

  async list(
    eventUuid: string,
    params: Record<string, unknown> = {}
  ): Promise<EmsPaginated<EmsEventNotification>> {
    const envelope = await emsHttp.getWithMeta<EmsEventNotification[]>(
      `/events/${eventUuid}/notifications`,
      params
    );
    return toPaginated(envelope.data, envelope.meta);
  },

  async listAll(
    params: Record<string, unknown> = {}
  ): Promise<EmsPaginated<EmsEventNotification>> {
    const envelope = await emsHttp.getWithMeta<EmsEventNotification[]>(
      '/notifications',
      params
    );
    return toPaginated(envelope.data, envelope.meta);
  },

  getOne(notificationUuid: string): Promise<EmsEventNotification> {
    return emsHttp.get(`/notifications/${notificationUuid}`);
  },

  resend(
    eventUuid: string,
    payload: { type: string; registration_uuid: string }
  ): Promise<null> {
    return emsHttp.post(`/events/${eventUuid}/notifications/resend`, payload);
  },

  retry(eventUuid: string, notificationUuid: string): Promise<EmsEventNotification> {
    return emsHttp.post(`/events/${eventUuid}/notifications/${notificationUuid}/retry`);
  },

  retryGlobal(notificationUuid: string): Promise<EmsEventNotification> {
    return emsHttp.post(`/notifications/${notificationUuid}/retry`);
  },

  listReminders(eventUuid: string): Promise<EmsEventReminder[]> {
    return emsHttp.get(`/events/${eventUuid}/reminders`);
  },

  createReminder(eventUuid: string, payload: ReminderPayload): Promise<EmsEventReminder> {
    return emsHttp.post(`/events/${eventUuid}/reminders`, payload);
  },

  updateReminder(
    eventUuid: string,
    reminderUuid: string,
    payload: Partial<ReminderPayload>
  ): Promise<EmsEventReminder> {
    return emsHttp.put(`/events/${eventUuid}/reminders/${reminderUuid}`, payload);
  },

  deleteReminder(eventUuid: string, reminderUuid: string): Promise<null> {
    return emsHttp.delete(`/events/${eventUuid}/reminders/${reminderUuid}`);
  },

  listTemplates(): Promise<EmsEmailTemplate[]> {
    return emsHttp.get('/email-templates');
  },

  updateTemplate(
    templateUuid: string,
    payload: Partial<Pick<EmsEmailTemplate, 'name' | 'subject' | 'body_html' | 'body_text' | 'is_active'>>
  ): Promise<EmsEmailTemplate> {
    return emsHttp.put(`/email-templates/${templateUuid}`, payload);
  },

  getPreferences(): Promise<EmsNotificationPreferences> {
    return emsHttp.get('/notification-preferences');
  },

  updatePreferences(
    payload: Partial<EmsNotificationPreferences>
  ): Promise<EmsNotificationPreferences> {
    return emsHttp.put('/notification-preferences', payload);
  },
};
