import { defineStore } from 'pinia';
import { ref } from 'vue';
import { notificationService } from '@/services/notification.service';
import { useDashboardStore } from './dashboard';
import type {
  NotificationItem,
  NotificationPreference,
  UpdatePreferencesPayload,
  NotificationFilterParams,
} from '@/types/notification';
import type { ApiError } from '@/types/api';

export const useNotificationsStore = defineStore('connect_notifications', () => {
  const notifications = ref<NotificationItem[]>([]);
  const unreadCount = ref<number>(0);
  const preferences = ref<NotificationPreference | null>(null);

  const pagination = ref<{
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  } | null>(null);

  const isLoading = ref<boolean>(false);
  const isLoadingPreferences = ref<boolean>(false);
  const isSavingPreferences = ref<boolean>(false);
  const isMarkingAllRead = ref<boolean>(false);
  const error = ref<string | null>(null);

  // Active filters
  const filterMode = ref<'all' | 'unread'>('all');
  const searchQuery = ref<string>('');

  /**
   * Fetch paginated notifications with optional query filters.
   */
  async function fetchNotifications(page = 1): Promise<void> {
    isLoading.value = true;
    error.value = null;

    const params: NotificationFilterParams = {
      page,
    };

    if (filterMode.value === 'unread') {
      params.unread = true;
    }

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    try {
      const response = await notificationService.getNotifications(params);
      notifications.value = response.data || [];
      pagination.value = {
        current_page: response.current_page,
        per_page: response.per_page,
        total: response.total,
        last_page: response.last_page,
      };
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      error.value = apiErr.message || 'Unable to load notifications.';
      notifications.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch live unread count summary and sync with dashboard.
   */
  async function fetchUnreadCount(): Promise<void> {
    try {
      const result = await notificationService.getUnreadSummary();
      unreadCount.value = result.unread_count;

      // Sync unread count into dashboard store if active
      const dashboardStore = useDashboardStore();
      if (dashboardStore.data?.notifications) {
        dashboardStore.data.notifications.unread_count = result.unread_count;
      }
    } catch (e) {
      console.warn('Failed to fetch unread count:', e);
    }
  }

  /**
   * Mark a single notification as read with reversible optimistic update.
   */
  async function markAsRead(id: string | number): Promise<boolean> {
    const item = notifications.value.find((n) => n.id === id || n.uuid === String(id));
    if (!item || item.read_at) return true;

    // Snapshot previous state for rollback
    const prevReadAt = item.read_at;
    const prevUnreadCount = unreadCount.value;

    // Optimistic mutation
    item.read_at = new Date().toISOString();
    if (unreadCount.value > 0) {
      unreadCount.value -= 1;
    }

    // Sync with dashboard
    const dashboardStore = useDashboardStore();
    if (dashboardStore.data?.notifications && dashboardStore.data.notifications.unread_count > 0) {
      dashboardStore.data.notifications.unread_count -= 1;
    }

    try {
      await notificationService.markAsRead(id);
      return true;
    } catch (err: unknown) {
      // Revert on server error
      item.read_at = prevReadAt;
      unreadCount.value = prevUnreadCount;
      if (dashboardStore.data?.notifications) {
        dashboardStore.data.notifications.unread_count = prevUnreadCount;
      }
      return false;
    }
  }

  /**
   * Mark all notifications as read.
   */
  async function markAllAsRead(): Promise<boolean> {
    if (isMarkingAllRead.value) return false;

    isMarkingAllRead.value = true;

    // Snapshot
    const prevUnreadCount = unreadCount.value;
    const prevItems = notifications.value.map((n) => ({ ...n }));

    // Optimistic
    unreadCount.value = 0;
    notifications.value.forEach((n) => {
      if (!n.read_at) n.read_at = new Date().toISOString();
    });

    const dashboardStore = useDashboardStore();
    if (dashboardStore.data?.notifications) {
      dashboardStore.data.notifications.unread_count = 0;
    }

    try {
      await notificationService.markAllAsRead();
      return true;
    } catch (err) {
      // Revert
      unreadCount.value = prevUnreadCount;
      notifications.value = prevItems;
      if (dashboardStore.data?.notifications) {
        dashboardStore.data.notifications.unread_count = prevUnreadCount;
      }
      return false;
    } finally {
      isMarkingAllRead.value = false;
    }
  }

  /**
   * Delete a notification permanently.
   */
  async function deleteNotification(id: string | number): Promise<boolean> {
    try {
      await notificationService.deleteNotification(id);
      notifications.value = notifications.value.filter((n) => n.id !== id && n.uuid !== String(id));
      await fetchUnreadCount();
      return true;
    } catch (err) {
      return false;
    }
  }

  /**
   * Fetch member notification preferences from backend.
   */
  async function fetchPreferences(force = false): Promise<void> {
    if (!force && preferences.value) return;

    isLoadingPreferences.value = true;
    try {
      preferences.value = await notificationService.getPreferences();
    } catch (err) {
      console.warn('Failed to load notification preferences:', err);
    } finally {
      isLoadingPreferences.value = false;
    }
  }

  /**
   * Update member notification preferences with backend persistence.
   */
  async function updatePreferences(payload: UpdatePreferencesPayload): Promise<boolean> {
    isSavingPreferences.value = true;
    try {
      const response = await notificationService.updatePreferences(payload);
      preferences.value = response.preferences;
      return true;
    } catch (err) {
      return false;
    } finally {
      isSavingPreferences.value = false;
    }
  }

  /**
   * Clear state completely upon logout to prevent data leakage between users.
   */
  function clearState() {
    notifications.value = [];
    unreadCount.value = 0;
    preferences.value = null;
    pagination.value = null;
    error.value = null;
    searchQuery.value = '';
    filterMode.value = 'all';
  }

  return {
    notifications,
    unreadCount,
    preferences,
    pagination,
    isLoading,
    isLoadingPreferences,
    isSavingPreferences,
    isMarkingAllRead,
    error,
    filterMode,
    searchQuery,
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    fetchPreferences,
    updatePreferences,
    clearState,
  };
});
