import client from '@/services/api';

export interface Notification {
  id: number;
  uuid: string;
  user_id: number;
  type: string;
  title: string;
  message: string;
  data: any;
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface NotificationPreferences {
  id: number;
  user_id: number;
  course_completion: boolean;
  new_announcements: boolean;
  upcoming_training: boolean;
  certificate_earned: boolean;
  email_enabled: boolean;
  in_app_enabled: boolean;
}

export interface NotificationLog {
  id: number;
  user_id: number;
  notification_type: string;
  channel: string;
  status: string;
  error_message: string | null;
  sent_at: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface NotificationStats {
  total_sent: number;
  total_failed: number;
  in_app_sent: number;
  email_sent: number;
  read_rate: number;
  failure_rate: number;
  unread_count: number;
}

export const notificationsService = {
  // User endpoints
  getNotifications(params?: { unread?: boolean; read?: boolean; category?: string; search?: string; page?: number }) {
    return client.get('/notifications', { params }).then(res => res.data);
  },

  getUnread() {
    return client.get('/notifications/unread').then(res => res.data);
  },

  markAsRead(uuid: string) {
    return client.post(`/notifications/${uuid}/read`).then(res => res.data);
  },

  markAllAsRead() {
    return client.post('/notifications/read-all').then(res => res.data);
  },

  deleteNotification(uuid: string) {
    return client.delete(`/notifications/${uuid}`).then(res => res.data);
  },

  getPreferences() {
    return client.get('/notifications/preferences').then(res => res.data);
  },

  updatePreferences(preferences: Partial<NotificationPreferences>) {
    return client.put('/notifications/preferences', preferences).then(res => res.data);
  },

  // Admin endpoints
  getLogs(params?: { status?: string; channel?: string; search?: string; page?: number }) {
    return client.get('/admin/notifications/logs', { params }).then(res => res.data);
  },

  resend(logId: number) {
    return client.post(`/admin/notifications/resend/${logId}`).then(res => res.data);
  },

  broadcast(data: { title: string; message: string; audience: string }) {
    return client.post('/admin/notifications/broadcast', data).then(res => res.data);
  },

  getStats() {
    return client.get('/admin/notifications/stats').then(res => res.data);
  }
};
