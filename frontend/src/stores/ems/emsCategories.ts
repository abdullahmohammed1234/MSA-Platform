import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { categoriesService, toEmsApiError } from '@/services/ems';
import type { EventCategory, EventCategoryFilters, EventCategoryPayload } from '@/types/ems';

export const useEmsCategoriesStore = defineStore('emsCategories', () => {
  const categories = ref<EventCategory[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const error = ref<string | null>(null);

  /** Loaded at least once, so selects can skip a redundant round trip. */
  const isLoaded = ref(false);

  /** Only active categories may be assigned to new events. */
  const activeCategories = computed(() => categories.value.filter((category) => category.is_active));

  /** Options shaped for the design-system Select component. */
  const selectOptions = computed(() =>
    activeCategories.value.map((category) => ({ label: category.name, value: category.id }))
  );

  const fetchAll = async (filters: EventCategoryFilters = {}): Promise<EventCategory[]> => {
    isLoading.value = true;
    error.value = null;

    try {
      categories.value = await categoriesService.list(filters);
      isLoaded.value = true;
      return categories.value;
    } catch (caught) {
      const apiError = toEmsApiError(caught);
      error.value = apiError.message;
      categories.value = [];
      throw apiError;
    } finally {
      isLoading.value = false;
    }
  };

  /** Load once; used by the event form to populate its category select. */
  const ensureLoaded = async (): Promise<EventCategory[]> => {
    if (isLoaded.value) {
      return categories.value;
    }

    try {
      return await fetchAll();
    } catch {
      return [];
    }
  };

  const create = async (payload: EventCategoryPayload): Promise<EventCategory> => {
    isSaving.value = true;

    try {
      const category = await categoriesService.create(payload);
      categories.value = sorted([...categories.value, category]);
      return category;
    } finally {
      isSaving.value = false;
    }
  };

  const update = async (uuid: string, payload: Partial<EventCategoryPayload>): Promise<EventCategory> => {
    isSaving.value = true;

    try {
      const category = await categoriesService.update(uuid, payload);
      categories.value = sorted(
        categories.value.map((existing) => (existing.uuid === uuid ? category : existing))
      );
      return category;
    } finally {
      isSaving.value = false;
    }
  };

  const remove = async (uuid: string): Promise<void> => {
    await categoriesService.remove(uuid);
    categories.value = categories.value.filter((category) => category.uuid !== uuid);
  };

  /** Mirrors the backend ordering: sort_order first, then name. */
  const sorted = (list: EventCategory[]): EventCategory[] =>
    [...list].sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name));

  return {
    categories,
    activeCategories,
    selectOptions,
    isLoading,
    isSaving,
    isLoaded,
    error,
    fetchAll,
    ensureLoaded,
    create,
    update,
    remove,
  };
});
