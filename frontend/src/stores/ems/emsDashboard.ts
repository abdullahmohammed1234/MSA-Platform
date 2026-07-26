import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { dashboardService, toEmsApiError } from '@/services/ems';
import type { EmsDashboard } from '@/types/ems';

export const useEmsDashboardStore = defineStore('emsDashboard', () => {
  const dashboard = ref<EmsDashboard | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  const summary = computed(() => dashboard.value?.summary ?? null);
  const upcomingEvents = computed(() => dashboard.value?.upcoming_events ?? []);
  const recentActivity = computed(() => dashboard.value?.recent_activity ?? []);

  /**
   * Quick actions arrive already filtered by the caller's permissions, so the
   * frontend does not repeat the mapping.
   */
  const quickActions = computed(() => dashboard.value?.quick_actions ?? []);

  const fetch = async (): Promise<void> => {
    isLoading.value = true;
    error.value = null;

    try {
      dashboard.value = await dashboardService.show();
    } catch (caught) {
      const apiError = toEmsApiError(caught);
      error.value = apiError.message;
      dashboard.value = null;
    } finally {
      isLoading.value = false;
    }
  };

  return {
    dashboard,
    summary,
    upcomingEvents,
    recentActivity,
    quickActions,
    isLoading,
    error,
    fetch,
  };
});
