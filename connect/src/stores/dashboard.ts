import { defineStore } from 'pinia';
import { ref } from 'vue';
import { dashboardService } from '@/services/dashboard.service';
import { announcementService } from '@/services/announcement.service';
import type { DashboardData } from '@/types/dashboard';
import type { Announcement } from '@/types/announcement';
import type { ApiError } from '@/types/api';

export const useDashboardStore = defineStore('connect_dashboard', () => {
  const data = ref<DashboardData | null>(null);
  const announcements = ref<Announcement[]>([]);
  const isLoading = ref<boolean>(false);
  const error = ref<string | null>(null);
  const lastFetchedAt = ref<number | null>(null);

  /**
   * Fetch authenticated user's aggregated dashboard data.
   * Caches for 60 seconds to avoid unnecessary network calls during rapid tab switching.
   */
  async function fetchDashboard(force = false): Promise<void> {
    const now = Date.now();
    if (!force && data.value && lastFetchedAt.value && now - lastFetchedAt.value < 60000) {
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const result = await dashboardService.getDashboard();
      data.value = result;
      lastFetchedAt.value = Date.now();
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      error.value = apiErr.message || 'Unable to load member dashboard.';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch published announcements.
   */
  async function fetchAnnouncements(force = false): Promise<void> {
    if (!force && announcements.value.length > 0) {
      return;
    }

    try {
      announcements.value = await announcementService.getAnnouncements();
    } catch (err) {
      console.warn('Failed to load announcements:', err);
    }
  }

  /**
   * Invalidate cached state so next view mount refreshes immediately.
   */
  function invalidateCache() {
    lastFetchedAt.value = null;
  }

  /**
   * Clear state completely upon logout to prevent data leakage between users.
   */
  function clearState() {
    data.value = null;
    announcements.value = [];
    error.value = null;
    lastFetchedAt.value = null;
  }

  return {
    data,
    announcements,
    isLoading,
    error,
    fetchDashboard,
    fetchAnnouncements,
    invalidateCache,
    clearState,
  };
});
