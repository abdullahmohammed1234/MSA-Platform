/**
 * Phase 5 — EMS communications types.
 */

export type EmsNotificationStatus = 'pending' | 'scheduled' | 'sent' | 'failed' | 'cancelled';

export interface EmsNotificationSummary {
  total: number;
  queued: number;
  sent: number;
  failed: number;
  cancelled: number;
  pending_reminders: number;
  by_type: Record<string, number>;
  types: Array<{
    value: string;
    label: string;
    category: string;
    resendable: boolean;
  }>;
}

export interface EmsEventNotification {
  uuid: string;
  type: string;
  channel: string;
  status: EmsNotificationStatus | string;
  queue_status: string | null;
  subject: string | null;
  recipient_email: string | null;
  template_key: string | null;
  retry_count: number;
  error: string | null;
  scheduled_at: string | null;
  queued_at: string | null;
  last_attempt_at: string | null;
  sent_at: string | null;
  failed_at: string | null;
  alert_sent_at: string | null;
  provider_message_id: string | null;
  created_at: string | null;
  registration?: {
    uuid: string;
    reference: string;
    attendee_name: string;
    attendee_email: string;
  } | null;
  event?: {
    uuid: string;
    name: string;
  } | null;
  event_uuid?: string | null;
}

export interface EmsEventReminder {
  uuid: string;
  label: string;
  offset_value: number;
  offset_unit: 'minutes' | 'hours' | 'days' | string;
  enabled: boolean;
  template_key: string;
  audience: 'all' | 'confirmed' | 'ticket_holders' | string;
  next_run_at: string | null;
  last_run_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EmsEmailTemplate {
  uuid: string;
  key: string;
  name: string;
  category: string;
  subject: string;
  body_html: string;
  body_text: string | null;
  placeholders: string[];
  is_active: boolean;
  is_system: boolean;
  updated_at: string | null;
}

export interface EmsNotificationPreferences {
  event_reminders: boolean;
  event_updates: boolean;
  feedback_requests: boolean;
  marketing_emails: boolean;
  post_event: boolean;
}

export type NotifyAudience = 'everyone' | 'registered' | 'ticket_holders' | 'none';

export interface ReminderPayload {
  label?: string | null;
  offset_value: number;
  offset_unit: 'minutes' | 'hours' | 'days';
  enabled?: boolean;
  template_key?: string;
  audience?: 'all' | 'confirmed' | 'ticket_holders';
}
