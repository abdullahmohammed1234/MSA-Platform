<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { useToastStore } from '@/components/feedback/toast';
import { EmsDateTimeField, EmsErrorState, EmsPageHeader } from '@/components/ems';
import ImageInput from '@/components/admin/ImageInput.vue';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsCategoriesStore } from '@/stores/ems/emsCategories';
import { useEmsTemplatesStore } from '@/stores/ems/emsTemplates';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EventPayload } from '@/types/ems';

/**
 * Create and edit an event.
 *
 * One component for both, because the fields are identical — the difference is
 * whether it POSTs or PUTs. `status` is deliberately absent: it is not an
 * editable field, only the lifecycle endpoint may change it.
 *
 * Validation here is a convenience. The server validates every field again and
 * its 422 response is what populates the inline errors below.
 */
const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const events = useEmsEventsStore();
const categories = useEmsCategoriesStore();
const templatesStore = useEmsTemplatesStore();
const { fieldError, generalError, handle, clear } = useEmsApiError();
const { toDateTimeLocal, fromDateTimeLocal } = useEventFormatting();

const uuid = computed(() => route.params.uuid as string | undefined);
const isEditing = computed(() => Boolean(uuid.value));
const isLoading = ref(false);
const loadError = ref<string | null>(null);

const UNCATEGORISED = 'none';

const form = reactive({
  name: '',
  short_description: '',
  description: '',
  category_id: UNCATEGORISED as string,
  location: '',
  organizer_name: '',
  start_at: '',
  end_at: '',
  capacity: '',
  is_public: false,
  banner_url: '',
  notify_audience: 'none' as 'everyone' | 'registered' | 'ticket_holders' | 'none',
});

const categoryOptions = computed(() => [
  { label: 'Uncategorised', value: UNCATEGORISED },
  ...categories.activeCategories.map((category) => ({
    label: category.name,
    value: String(category.id),
  })),
]);

const notifyAudienceOptions = [
  { value: 'none', label: 'Do not notify' },
  { value: 'registered', label: 'Notify registered attendees' },
  { value: 'ticket_holders', label: 'Notify ticket holders' },
  { value: 'everyone', label: 'Notify everyone' },
];

const selectedTemplateUuid = ref('');
const templateOptions = computed(() => [
  { label: 'Start from scratch / No template', value: '' },
  ...templatesStore.templates.map((t) => ({
    label: t.name,
    value: t.uuid,
  })),
]);

watch(selectedTemplateUuid, (val) => {
  if (!val) return;
  const template = templatesStore.templates.find((t) => t.uuid === val);
  if (template) {
    form.name = template.name;
    form.description = template.description ?? '';
    form.category_id = template.category_id ? String(template.category_id) : UNCATEGORISED;
    form.capacity = template.capacity ? String(template.capacity) : '';
    form.is_public = template.is_public;
    toast.success(`Applied template: ${template.name}`);
  }
});

onMounted(async () => {
  void categories.ensureLoaded();

  if (!isEditing.value) {
    void templatesStore.ensureLoaded();

    const duplicateFrom = route.query.duplicate_from as string | undefined;
    if (duplicateFrom) {
      isLoading.value = true;
      try {
        const orig = await events.fetchOne(duplicateFrom);
        if (orig) {
          form.name = orig.name + ' (Copy)';
          form.short_description = orig.short_description ?? '';
          form.description = orig.description ?? '';
          form.category_id = orig.category_id ? String(orig.category_id) : UNCATEGORISED;
          form.location = orig.location ?? '';
          form.organizer_name = orig.organizer_name ?? '';
          form.start_at = toDateTimeLocal(orig.start_at);
          form.end_at = toDateTimeLocal(orig.end_at);
          form.capacity = orig.capacity ? String(orig.capacity) : '';
          form.is_public = orig.is_public;
          form.banner_url = orig.banner_url ?? '';
          toast.info('Copied details from original event.');
        }
      } catch (caught) {
        loadError.value = handle(caught, { silent: true }).message;
      } finally {
        isLoading.value = false;
      }
    }
    return;
  }

  isLoading.value = true;

  try {
    const event = await events.fetchOne(uuid.value!);

    if (event) {
      form.name = event.name;
      form.short_description = event.short_description ?? '';
      form.description = event.description ?? '';
      form.category_id = event.category_id ? String(event.category_id) : UNCATEGORISED;
      form.location = event.location ?? '';
      form.organizer_name = event.organizer_name ?? '';
      form.start_at = toDateTimeLocal(event.start_at);
      form.end_at = toDateTimeLocal(event.end_at);
      form.capacity = event.capacity ? String(event.capacity) : '';
      form.is_public = event.is_public;
      form.banner_url = event.banner_url ?? '';
    }
  } catch (caught) {
    loadError.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
});

const buildPayload = (): EventPayload => ({
  name: form.name.trim(),
  short_description: form.short_description.trim() || null,
  description: form.description.trim() || null,
  category_id: form.category_id === UNCATEGORISED ? null : Number(form.category_id),
  location: form.location.trim() || null,
  organizer_name: form.organizer_name.trim() || null,
  start_at: fromDateTimeLocal(form.start_at) ?? '',
  end_at: fromDateTimeLocal(form.end_at),
  capacity: form.capacity ? Number(form.capacity) : null,
  is_public: form.is_public,
  banner_url: form.banner_url.trim() || null,
  ...(isEditing.value ? { notify_audience: form.notify_audience } : {}),
});

const submit = async () => {
  clear();

  try {
    const payload = buildPayload();

    const event = isEditing.value
      ? await events.update(uuid.value!, payload)
      : await events.create(payload);

    toast.success(isEditing.value ? 'Event updated successfully.' : 'Event created successfully.');
    await router.push({ name: 'ems-event-detail', params: { uuid: event.uuid } });
  } catch (caught) {
    handle(caught);
  }
};

const cancel = () =>
  isEditing.value
    ? router.push({ name: 'ems-event-detail', params: { uuid: uuid.value } })
    : router.push({ name: 'ems-events' });
</script>

<template>
  <div class="max-w-3xl">
    <EmsPageHeader
      :title="isEditing ? 'Edit Event' : 'Create Event'"
      :description="
        isEditing
          ? 'Update the event details. Status changes happen from the event page.'
          : 'New events start as a draft. Publish them from the event page when ready.'
      "
      back-to="/ems/events"
      back-label="All events"
    />

    <EmsErrorState v-if="loadError" :message="loadError" :can-retry="false" />

    <div v-else-if="isLoading" class="space-y-4" role="status" aria-label="Loading event">
      <div v-for="row in 5" :key="row" class="h-14 animate-pulse rounded-xl bg-neutral-ivory/60" />
    </div>

    <form v-else class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft sm:p-6" @submit.prevent="submit">
      <p
        v-if="generalError"
        class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-secondary"
        role="alert"
      >
        {{ generalError }}
      </p>

      <fieldset class="space-y-0" :disabled="events.isSaving">
        <legend class="sr-only">Event details</legend>

        <Select
          v-if="!isEditing"
          v-model="selectedTemplateUuid"
          label="Pre-fill from Template"
          description="Optionally load preset details from an event template."
          :options="templateOptions"
          class="mb-6"
        />

        <Input
          v-model="form.name"
          label="Event name"
          placeholder="MSA Welcome Night"
          required
          :error="fieldError('name')"
        />

        <Input
          v-model="form.short_description"
          label="Short description"
          description="A one-line summary shown in listings."
          placeholder="An evening to welcome new students."
          :error="fieldError('short_description')"
        />

        <Textarea
          v-model="form.description"
          label="Description"
          :rows="5"
          placeholder="Full details about the event."
          :error="fieldError('description')"
        />

        <ImageInput
          v-model="form.banner_url"
          label="Event Banner"
          hint="Upload an image from your device or paste a link. Best ratio is 16:9."
          placeholder="Paste an image link (https://...) or /Hero/photo.webp"
          preview-class="w-full max-h-48 object-cover rounded-xl mt-2"
          class="mb-6"
          :error="fieldError('banner_url')"
        />

        <div class="grid gap-x-4 sm:grid-cols-2">
          <EmsDateTimeField
            v-model="form.start_at"
            label="Starts at"
            required
            :error="fieldError('start_at')"
          />

          <EmsDateTimeField
            v-model="form.end_at"
            label="Ends at"
            description="Optional. Must be after the start."
            :min="form.start_at"
            :error="fieldError('end_at')"
          />
        </div>

        <div class="grid gap-x-4 sm:grid-cols-2">
          <Select
            v-model="form.category_id"
            label="Category"
            :options="categoryOptions"
            :error="fieldError('category_id')"
          />

          <Input
            v-model="form.capacity"
            type="number"
            label="Capacity"
            description="Leave blank for unlimited."
            :error="fieldError('capacity')"
          />
        </div>

        <Input
          v-model="form.location"
          label="Location"
          placeholder="SFU Burnaby, Student Union Building"
          :error="fieldError('location')"
        />

        <Input
          v-model="form.organizer_name"
          label="Organizer Name"
          description="Custom organizer name (e.g. 'MSA Dawah Committee'). Falls back to default if blank."
          placeholder="MSA Dawah Committee"
          :error="fieldError('organizer_name')"
        />

        <Switch
          v-model="form.is_public"
          label="Publicly listable"
          description="Marks the event as eligible for public discovery once it is published."
        />

        <Select
          v-if="isEditing"
          v-model="form.notify_audience"
          label="Notify attendees of changes"
          description="Choose who should receive an update email if meaningful fields change."
          :options="notifyAudienceOptions"
        />
      </fieldset>

      <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-neutral-ivory pt-5">
        <Button variant="ghost" type="button" :disabled="events.isSaving" @click="cancel">
          Cancel
        </Button>
        <Button variant="primary" type="submit" :is-loading="events.isSaving">
          {{ isEditing ? 'Save changes' : 'Create event' }}
        </Button>
      </div>
    </form>
  </div>
</template>
