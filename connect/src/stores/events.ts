import { defineStore } from 'pinia';
import { ref } from 'vue';
import { emsService } from '@/services/ems.service';
import type {
  PublicEvent,
  PublicEventDetail,
  PublicCategory,
  CalendarLinks,
  PaginationMeta,
  EventFilterParams,
} from '@/types/ems';
import type { ApiError } from '@/types/api';

export const useEventsStore = defineStore('connect_events', () => {
  const events = ref<PublicEvent[]>([]);
  const categories = ref<PublicCategory[]>([]);
  const currentEvent = ref<PublicEventDetail | null>(null);
  const calendarLinks = ref<CalendarLinks | null>(null);
  const pagination = ref<PaginationMeta | null>(null);

  const isLoading = ref<boolean>(false);
  const isLoadingDetail = ref<boolean>(false);
  const error = ref<string | null>(null);
  const detailError = ref<string | null>(null);

  // Active filter state
  const searchQuery = ref<string>('');
  const selectedCategory = ref<string>('');
  const timeFilter = ref<'upcoming' | 'past' | 'all'>('upcoming');

  /**
   * Fetch events with current or custom filter parameters.
   */
  async function fetchEvents(customParams?: EventFilterParams): Promise<void> {
    isLoading.value = true;
    error.value = null;

    const params: EventFilterParams = {
      search: customParams?.search ?? (searchQuery.value.trim() || undefined),
      category_slug: customParams?.category_slug ?? (selectedCategory.value || undefined),
      page: customParams?.page ?? 1,
      per_page: customParams?.per_page ?? 12,
    };

    const activeTimeFilter = customParams?.upcoming !== undefined ? undefined : timeFilter.value;
    if (activeTimeFilter === 'upcoming') {
      params.upcoming = true;
    } else if (activeTimeFilter === 'past') {
      params.past = true;
    }

    try {
      const response = await emsService.getEvents(params);
      events.value = response.data || [];
      pagination.value = response.meta?.pagination || null;
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      error.value = apiErr.message || 'Unable to load events catalog.';
      events.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch EMS categories list (cached after first successful call).
   */
  async function fetchCategories(force = false): Promise<void> {
    if (!force && categories.value.length > 0) return;

    try {
      const response = await emsService.getCategories();
      categories.value = response.data || [];
    } catch (err) {
      console.warn('Failed to load event categories:', err);
    }
  }

  /**
   * Fetch single event details by slug.
   */
  async function fetchEventBySlug(slug: string): Promise<PublicEventDetail | null> {
    isLoadingDetail.value = true;
    detailError.value = null;
    currentEvent.value = null;
    calendarLinks.value = null;

    try {
      const response = await emsService.getEventBySlug(slug);
      currentEvent.value = response.data;
      return response.data;
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      detailError.value = apiErr.message || 'Event not found or currently unavailable.';
      return null;
    } finally {
      isLoadingDetail.value = false;
    }
  }

  /**
   * Fetch calendar links for current event.
   */
  async function fetchCalendarLinks(slug: string): Promise<void> {
    try {
      const response = await emsService.getCalendarLinks(slug);
      calendarLinks.value = response.data;
    } catch (err) {
      console.warn('Failed to fetch calendar links:', err);
    }
  }

  /**
   * Clear active event detail state.
   */
  function clearCurrentEvent() {
    currentEvent.value = null;
    calendarLinks.value = null;
    detailError.value = null;
  }

  /**
   * Helper to get ICS download stream URL.
   */
  function getIcsUrl(slug: string): string {
    return emsService.getIcsDownloadUrl(slug);
  }

  /**
   * Clear state completely upon logout to prevent data leakage between users.
   */
  function clearState() {
    currentEvent.value = null;
    calendarLinks.value = null;
    detailError.value = null;
    error.value = null;
    events.value = [];
    pagination.value = null;
    searchQuery.value = '';
    selectedCategory.value = '';
    timeFilter.value = 'upcoming';
  }

  return {
    events,
    categories,
    currentEvent,
    calendarLinks,
    pagination,
    isLoading,
    isLoadingDetail,
    error,
    detailError,
    searchQuery,
    selectedCategory,
    timeFilter,
    fetchEvents,
    fetchCategories,
    fetchEventBySlug,
    fetchCalendarLinks,
    clearCurrentEvent,
    getIcsUrl,
    clearState,
  };
});
