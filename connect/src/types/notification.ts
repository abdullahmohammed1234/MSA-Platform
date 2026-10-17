/**
 * Types matching Laravel Notification and NotificationPreference API contracts.
 */

export interface NotificationItem {
  id: number;
  uuid: string;
  user_id?: number;
  type: string;
  title: string;
  message: string;
  data?: {
    action_url?: string;
    action_label?: string;
    type?: string;
    [key: string]: any;
  } | null;
  read_at: string | null;
  created_at: string;
  updated_at?: string;
}

export interface PaginatedNotifications {
  data: NotificationItem[];
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface UnreadSummaryResponse {
  unread_count: number;
  latest_unread: NotificationItem[];
}

export interface NotificationPreference {
  id?: number;
  user_id?: number;
  course_completion: boolean;
  new_announcements: boolean;
  upcoming_training: boolean;
  certificate_earned: boolean;
  support_updates: boolean;
  direct_messages: boolean;
  email_enabled: boolean;
  in_app_enabled: boolean;
  push_enabled: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface UpdatePreferencesPayload {
  course_completion?: boolean;
  new_announcements?: boolean;
  upcoming_training?: boolean;
  certificate_earned?: boolean;
  support_updates?: boolean;
  direct_messages?: boolean;
  email_enabled?: boolean;
  in_app_enabled?: boolean;
  push_enabled?: boolean;
}

export interface NotificationFilterParams {
  page?: number;
  unread?: boolean;
  read?: boolean;
  category?: string;
  search?: string;
}

export interface PushConfigResponse {
  enabled: boolean;
  public_key: string | null;
}

export interface DeviceSubscriptionItem {
  uuid: string;
  device_name: string | null;
  device_type: string | null;
  created_at: string;
  last_used_at: string | null;
}

export interface RegisterSubscriptionPayload {
  endpoint: string;
  keys?: {
    p256dh: string;
    auth: string;
  };
  public_key?: string;
  auth_token?: string;
  content_encoding?: string;
  device_name?: string;
  device_type?: string;
  user_agent?: string;
}

