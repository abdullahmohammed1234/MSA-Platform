import { defineStore } from 'pinia';
import { ref } from 'vue';
import { 
  notificationsService, 
  type Notification, 
  type NotificationPreferences 
} from '@/services/notifications';

export interface Toast {
  id: string;
  message: string;
  type: 'success' | 'warning' | 'error' | 'info';
  duration?: number;
}

export const useNotificationsStore = defineStore('notifications', () => {
  // Toast notifications state (kept for UI feedback compatibility)
  const toasts = ref<Toast[]>([]);

  const addToast = (message: string, type: Toast['type'] = 'info', duration = 3000) => {
    const id = Math.random().toString(36).substring(2, 9);
    const toast: Toast = { id, message, type, duration };
    toasts.value.push(toast);

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }
  };

  const removeToast = (id: string) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
  };

  // Centralized notifications state
  const notifications = ref<Notification[]>([]);
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 15
  });
  const unreadCount = ref<number>(0);
  const latestUnread = ref<Notification[]>([]);
  const preferences = ref<NotificationPreferences | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  /**
   * Fetch paginated notifications.
   */
  const fetchNotifications = async (params?: { unread?: boolean; read?: boolean; category?: string; search?: string; page?: number }) => {
    loading.value = true;
    error.value = null;
    try {
      const data = await notificationsService.getNotifications(params);
      notifications.value = data.data;
      pagination.value = {
        currentPage: data.current_page,
        lastPage: data.last_page,
        total: data.total,
        perPage: data.per_page
      };
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load notifications.';
      addToast(error.value || '', 'error');
    } finally {
      loading.value = false;
    }
  };

  /**
   * Fetch unread count and latest unread notifications.
   */
  const fetchUnread = async () => {
    try {
      const data = await notificationsService.getUnread();
      unreadCount.value = data.unread_count;
      latestUnread.value = data.latest_unread;
    } catch (err: any) {
      console.error('Failed to load unread notifications:', err);
    }
  };

  /**
   * Mark a notification as read (with optimistic updates).
   */
  const markAsRead = async (uuid: string) => {
    // 1. Optimistic Update
    const originalNotifications = [...notifications.value];
    const originalLatestUnread = [...latestUnread.value];
    const originalCount = unreadCount.value;

    const notifIndex = notifications.value.findIndex(n => n.uuid === uuid);
    if (notifIndex !== -1 && !notifications.value[notifIndex].read_at) {
      notifications.value[notifIndex].read_at = new Date().toISOString();
      if (unreadCount.value > 0) {
        unreadCount.value--;
      }
    }
    latestUnread.value = latestUnread.value.filter(n => n.uuid !== uuid);

    // 2. Network Request
    try {
      await notificationsService.markAsRead(uuid);
      // Optional: re-fetch unread count/list to sync correctly
      fetchUnread();
    } catch (err: any) {
      // Rollback on failure
      notifications.value = originalNotifications;
      latestUnread.value = originalLatestUnread;
      unreadCount.value = originalCount;
      addToast(err.response?.data?.message || 'Failed to mark notification as read.', 'error');
    }
  };

  /**
   * Mark all user's notifications as read (with optimistic updates).
   */
  const markAllAsRead = async () => {
    // 1. Optimistic Update
    const originalNotifications = [...notifications.value];
    const originalLatestUnread = [...latestUnread.value];
    const originalCount = unreadCount.value;

    notifications.value = notifications.value.map(n => ({
      ...n,
      read_at: n.read_at || new Date().toISOString()
    }));
    latestUnread.value = [];
    unreadCount.value = 0;

    // 2. Network Request
    try {
      await notificationsService.markAllAsRead();
    } catch (err: any) {
      // Rollback on failure
      notifications.value = originalNotifications;
      latestUnread.value = originalLatestUnread;
      unreadCount.value = originalCount;
      addToast(err.response?.data?.message || 'Failed to mark all as read.', 'error');
    }
  };

  /**
   * Delete a notification (with optimistic updates).
   */
  const deleteNotification = async (uuid: string) => {
    // 1. Optimistic Update
    const originalNotifications = [...notifications.value];
    const originalLatestUnread = [...latestUnread.value];
    const originalCount = unreadCount.value;

    const target = notifications.value.find(n => n.uuid === uuid);
    notifications.value = notifications.value.filter(n => n.uuid !== uuid);
    latestUnread.value = latestUnread.value.filter(n => n.uuid !== uuid);
    if (target && !target.read_at && unreadCount.value > 0) {
      unreadCount.value--;
    }

    // 2. Network Request
    try {
      await notificationsService.deleteNotification(uuid);
      addToast('Notification removed successfully.', 'success');
    } catch (err: any) {
      // Rollback on failure
      notifications.value = originalNotifications;
      latestUnread.value = originalLatestUnread;
      unreadCount.value = originalCount;
      addToast(err.response?.data?.message || 'Failed to delete notification.', 'error');
    }
  };

  const lastFetchedPreferences = ref<number | null>(null);

  /**
   * Fetch user notification preferences.
   */
  const fetchPreferences = async (force = false) => {
    const now = Date.now();
    if (!force && lastFetchedPreferences.value && (now - lastFetchedPreferences.value < 5 * 60 * 1000) && preferences.value) {
      return;
    }

    loading.value = true;
    try {
      preferences.value = await notificationsService.getPreferences();
      lastFetchedPreferences.value = now;
    } catch (err: any) {
      console.error('Failed to load notification preferences:', err);
    } finally {
      loading.value = false;
    }
  };

  /**
   * Update user preferences.
   */
  const updatePreferences = async (newPrefs: Partial<NotificationPreferences>) => {
    loading.value = true;
    const originalPrefs = preferences.value ? { ...preferences.value } : null;
    
    // Optimistic Update
    if (preferences.value) {
      preferences.value = { ...preferences.value, ...newPrefs };
    }

    try {
      const data = await notificationsService.updatePreferences(newPrefs);
      preferences.value = data.preferences;
      lastFetchedPreferences.value = null; // invalidate
      addToast('Notification preferences updated successfully.', 'success');
    } catch (err: any) {
      // Rollback
      preferences.value = originalPrefs;
      addToast(err.response?.data?.message || 'Failed to update preferences.', 'error');
    } finally {
      loading.value = false;
    }
  };

  return {
    toasts,
    addToast,
    removeToast,
    notifications,
    pagination,
    unreadCount,
    latestUnread,
    preferences,
    loading,
    error,
    fetchNotifications,
    fetchUnread,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    fetchPreferences,
    updatePreferences
  };
});
