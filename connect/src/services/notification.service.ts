import apiClient from './api';
import type {
  NotificationItem,
  PaginatedNotifications,
  UnreadSummaryResponse,
  NotificationPreference,
  UpdatePreferencesPayload,
  NotificationFilterParams,
} from '@/types/notification';

export const notificationService = {
  /**
   * Fetch paginated list of member notifications.
   */
  async getNotifications(params: NotificationFilterParams = {}): Promise<PaginatedNotifications> {
    const response = await apiClient.get<PaginatedNotifications>('/notifications', { params });
    return response.data;
  },

  /**
   * Fetch quick unread notifications count and latest unread items.
   */
  async getUnreadSummary(): Promise<UnreadSummaryResponse> {
    const response = await apiClient.get<UnreadSummaryResponse>('/notifications/unread');
    return response.data;
  },

  /**
   * Mark a single notification as read by uuid or id.
   */
  async markAsRead(id: string | number): Promise<{ success: boolean; notification: NotificationItem }> {
    const response = await apiClient.post<{ success: boolean; notification: NotificationItem }>(
      `/notifications/${encodeURIComponent(String(id))}/read`
    );
    return response.data;
  },

  /**
   * Mark all unread notifications as read.
   */
  async markAllAsRead(): Promise<{ success: boolean; message: string }> {
    const response = await apiClient.post<{ success: boolean; message: string }>('/notifications/read-all');
    return response.data;
  },

  /**
   * Delete a notification permanently.
   */
  async deleteNotification(id: string | number): Promise<{ success: boolean; message: string }> {
    const response = await apiClient.delete<{ success: boolean; message: string }>(
      `/notifications/${encodeURIComponent(String(id))}`
    );
    return response.data;
  },

  /**
   * Get user notification preferences.
   */
  async getPreferences(): Promise<NotificationPreference> {
    const response = await apiClient.get<NotificationPreference>('/notifications/preferences');
    return response.data;
  },

  /**
   * Update user notification preferences.
   */
  async updatePreferences(payload: UpdatePreferencesPayload): Promise<{ success: boolean; preferences: NotificationPreference }> {
    const response = await apiClient.put<{ success: boolean; preferences: NotificationPreference }>(
      '/notifications/preferences',
      payload
    );
    return response.data;
  },
};

export default notificationService;
