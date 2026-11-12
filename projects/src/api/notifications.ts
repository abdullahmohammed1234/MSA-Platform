import apiClient from './client';

export interface PmNotification {
  id: number;
  uuid: string;
  user_id: number;
  type: string;
  title: string;
  message: string;
  data: {
    type?: string;
    project_id?: number;
    project_uuid?: string;
    task_id?: number;
    task_uuid?: string;
    actor_id?: number;
    actor_name?: string;
    action_url?: string;
    summary?: Record<string, any>;
    [key: string]: any;
  } | null;
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface NotificationsPaginatedResponse {
  current_page: number;
  data: PmNotification[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface UnreadNotificationsResponse {
  unread_count: number;
  latest_unread: PmNotification[];
}

export const notificationsApi = {
  async getNotifications(params?: {
    unread?: boolean;
    read?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<NotificationsPaginatedResponse> {
    const response = await apiClient.get<NotificationsPaginatedResponse>('/notifications', { params });
    return response.data;
  },

  async getUnread(): Promise<UnreadNotificationsResponse> {
    const response = await apiClient.get<UnreadNotificationsResponse>('/notifications/unread');
    return response.data;
  },

  async markAsRead(id: string | number): Promise<{ success: boolean; notification: PmNotification }> {
    const response = await apiClient.post<{ success: boolean; notification: PmNotification }>(`/notifications/${id}/read`);
    return response.data;
  },

  async markAllAsRead(): Promise<{ success: boolean; message: string }> {
    const response = await apiClient.post<{ success: boolean; message: string }>('/notifications/read-all');
    return response.data;
  },

  async deleteNotification(id: string | number): Promise<{ success: boolean; message: string }> {
    const response = await apiClient.delete<{ success: boolean; message: string }>(`/notifications/${id}`);
    return response.data;
  },
};

