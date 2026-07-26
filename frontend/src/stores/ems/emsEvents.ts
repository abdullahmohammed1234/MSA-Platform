import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { eventsService, toEmsApiError } from '@/services/ems';
import type { EmsApiError } from '@/services/ems';
import type {
  EmsPagination,
  Event,
  EventLifecycleGraph,
  EventListFilters,
  EventPayload,
  EventTransitionAction,
} from '@/types/ems';

const DEFAULT_FILTERS: EventListFilters = {
  search: '',
  status: '',
  category_id: null,
  sort_by: 'start_at',
  sort_direction: 'asc',
  per_page: 15,
  page: 1,
};

export const useEmsEventsStore = defineStore('emsEvents', () => {
  const events = ref<Event[]>([]);
  const current = ref<Event | null>(null);
  const lifecycle = ref<EventLifecycleGraph | null>(null);

  const filters = ref<EventListFilters>({ ...DEFAULT_FILTERS });
  const pagination = ref<EmsPagination>({
    current_page: 1,
    per_page: DEFAULT_FILTERS.per_page ?? 15,
    total: 0,
    last_page: 1,
    from: null,
    to: null,
  });

  const isLoadingList = ref(false);
  const isLoadingCurrent = ref(false);
  const isSaving = ref(false);
  const isTransitioning = ref(false);
  const listError = ref<string | null>(null);
  const currentError = ref<EmsApiError | null>(null);

  const isEmpty = computed(() => !isLoadingList.value && events.value.length === 0);

  /** Fetch a page of events using the current filters. */
  const fetchList = async (overrides: Partial<EventListFilters> = {}): Promise<void> => {
    filters.value = { ...filters.value, ...overrides };

    isLoadingList.value = true;
    listError.value = null;

    try {
      const result = await eventsService.list(filters.value);
      events.value = result.items;
      pagination.value = result.pagination;
    } catch (caught) {
      const error = toEmsApiError(caught);
      listError.value = error.message;
      events.value = [];
      throw error;
    } finally {
      isLoadingList.value = false;
    }
  };

  const goToPage = (page: number) => fetchList({ page });

  const resetFilters = () => {
    filters.value = { ...DEFAULT_FILTERS };
    return fetchList();
  };

  const fetchOne = async (uuid: string): Promise<Event | null> => {
    isLoadingCurrent.value = true;
    currentError.value = null;

    try {
      current.value = await eventsService.show(uuid);
      return current.value;
    } catch (caught) {
      const error = toEmsApiError(caught);
      current.value = null;
      currentError.value = error;
      throw error;
    } finally {
      isLoadingCurrent.value = false;
    }
  };

  const create = async (payload: EventPayload): Promise<Event> => {
    isSaving.value = true;

    try {
      const event = await eventsService.create(payload);
      current.value = event;
      return event;
    } finally {
      isSaving.value = false;
    }
  };

  const update = async (uuid: string, payload: Partial<EventPayload>): Promise<Event> => {
    isSaving.value = true;

    try {
      const event = await eventsService.update(uuid, payload);
      replaceInList(event);
      current.value = event;
      return event;
    } finally {
      isSaving.value = false;
    }
  };

  const remove = async (uuid: string): Promise<void> => {
    await eventsService.remove(uuid);
    events.value = events.value.filter((event) => event.uuid !== uuid);

    if (current.value?.uuid === uuid) {
      current.value = null;
    }
  };

  /**
   * Apply a lifecycle transition. The server returns the event in its new
   * state, including the next set of available transitions, so nothing here
   * needs to know the rules.
   */
  const transition = async (uuid: string, action: EventTransitionAction): Promise<Event> => {
    isTransitioning.value = true;

    try {
      const event = await eventsService.transition(uuid, action);
      replaceInList(event);

      if (current.value?.uuid === uuid) {
        current.value = event;
      }

      return event;
    } finally {
      isTransitioning.value = false;
    }
  };

  /** The published state graph, fetched once per session. */
  const fetchLifecycle = async (): Promise<EventLifecycleGraph | null> => {
    if (lifecycle.value) {
      return lifecycle.value;
    }

    try {
      lifecycle.value = await eventsService.lifecycle();
    } catch {
      // The graph is supplementary: the per-event action list still works.
      lifecycle.value = null;
    }

    return lifecycle.value;
  };

  const replaceInList = (event: Event) => {
    const index = events.value.findIndex((candidate) => candidate.uuid === event.uuid);

    if (index !== -1) {
      events.value[index] = event;
    }
  };

  return {
    events,
    current,
    lifecycle,
    filters,
    pagination,
    isLoadingList,
    isLoadingCurrent,
    isSaving,
    isTransitioning,
    listError,
    currentError,
    isEmpty,
    fetchList,
    goToPage,
    resetFilters,
    fetchOne,
    create,
    update,
    remove,
    transition,
    fetchLifecycle,
  };
});
