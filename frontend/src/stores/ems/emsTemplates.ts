import { defineStore } from 'pinia';
import { ref } from 'vue';
import { templatesService, toEmsApiError } from '@/services/ems';
import type { EventTemplate, EventTemplatePayload } from '@/types/ems';

export const useEmsTemplatesStore = defineStore('emsTemplates', () => {
  const templates = ref<EventTemplate[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const error = ref<string | null>(null);
  const isLoaded = ref(false);

  const fetchAll = async (): Promise<EventTemplate[]> => {
    isLoading.value = true;
    error.value = null;

    try {
      templates.value = await templatesService.list();
      isLoaded.value = true;
      return templates.value;
    } catch (caught) {
      const apiError = toEmsApiError(caught);
      error.value = apiError.message;
      templates.value = [];
      throw apiError;
    } finally {
      isLoading.value = false;
    }
  };

  const ensureLoaded = async (): Promise<EventTemplate[]> => {
    if (isLoaded.value) {
      return templates.value;
    }
    try {
      return await fetchAll();
    } catch {
      return [];
    }
  };

  const create = async (payload: EventTemplatePayload): Promise<EventTemplate> => {
    isSaving.value = true;
    try {
      const template = await templatesService.create(payload);
      templates.value.push(template);
      return template;
    } finally {
      isSaving.value = false;
    }
  };

  const update = async (uuid: string, payload: Partial<EventTemplatePayload>): Promise<EventTemplate> => {
    isSaving.value = true;
    try {
      const template = await templatesService.update(uuid, payload);
      templates.value = templates.value.map((existing) =>
        existing.uuid === uuid ? template : existing
      );
      return template;
    } finally {
      isSaving.value = false;
    }
  };

  const remove = async (uuid: string): Promise<void> => {
    await templatesService.remove(uuid);
    templates.value = templates.value.filter((template) => template.uuid !== uuid);
  };

  return {
    templates,
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
