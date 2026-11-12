import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { notificationsApi, type PmNotification, type NotificationsPaginatedResponse } from '../api/notifications';

export const useNotificationStore = defineStore('pmNotifications', () => {
  const notifications = ref<PmNotification[]>([]);
  const latestUnread = ref<PmNotification[]>([]);
  const unreadCount = ref<number>(0);
  const loading = ref<boolean>(false);
  const loadingMore = ref<boolean>(false);
  const error = ref<string | null>(null);
  const pagination = ref<NotificationsPaginatedResponse | null>(null);

  const hasUnread = computed(() => unreadCount.value > 0);

  async function fetchUnreadCount(): Promise<void> {
    try {
      const data = await notificationsApi.getUnread();
      unreadCount.value = data.unread_count;
      latestUnread.value = data.latest_unread || [];
    } catch (e: any) {
      // Suppress or log in background
      console.warn('Failed to load unread notifications count:', e?.message);
    }
  }

  async function fetchNotifications(params?: { unread?: boolean; search?: string; page?: number }): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      const data = await notificationsApi.getNotifications(params);
      pagination.value = data;
      notifications.value = data.data;
    } catch (e: any) {
      error.value = e?.response?.data?.message || e?.message || 'Failed to load notifications';
    } finally {
      loading.value = false;
    }
  }

  async function markAsRead(id: string | number): Promise<void> {
    try {
      await notificationsApi.markAsRead(id);
      
      // Update local state
      const target = notifications.value.find((n) => n.id === id || n.uuid === id);
      if (target && !target.read_at) {
        target.read_at = new Date().toISOString();
        if (unreadCount.value > 0) {
          unreadCount.value--;
        }
      }

      latestUnread.value = latestUnread.value.filter((n) => n.id !== id && n.uuid !== id);
    } catch (e: any) {
      console.error('Failed to mark notification as read:', e);
    }
  }

  async function markAllAsRead(): Promise<void> {
    try {
      await notificationsApi.markAllAsRead();
      notifications.value.forEach((n) => {
        n.read_at = new Date().toISOString();
      });
      latestUnread.value = [];
      unreadCount.value = 0;
    } catch (e: any) {
      console.error('Failed to mark all as read:', e);
    }
  }

  return {
    notifications,
    latestUnread,
    unreadCount,
    hasUnread,
    loading,
    loadingMore,
    error,
    pagination,
    fetchUnreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  };
});

