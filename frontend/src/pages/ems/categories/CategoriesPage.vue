<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Dialog } from '@/components/feedback/dialog';
import { useToastStore } from '@/components/feedback/toast';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';
import {
  EmsConfirmDialog,
  EmsErrorState,
  EmsPageHeader,
  EmsTableSkeleton,
} from '@/components/ems';
import { useEmsCategoriesStore } from '@/stores/ems/emsCategories';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { EMS_PERMISSIONS } from '@/constants/ems';
import type { EventCategory } from '@/types/ems';

/**
 * The event taxonomy.
 *
 * Categories are database-driven — nothing here is hard-coded — and each
 * action is gated on its own permission, then enforced again server-side.
 */
const toast = useToastStore();
const store = useEmsCategoriesStore();
const { can } = useEmsPermissions();
const { fieldError, generalError, handle, clear } = useEmsApiError();

const canCreate = computed(() => can(EMS_PERMISSIONS.CATEGORIES_CREATE));
const canUpdate = computed(() => can(EMS_PERMISSIONS.CATEGORIES_UPDATE));
const canDelete = computed(() => can(EMS_PERMISSIONS.CATEGORIES_DELETE));

const isFormOpen = ref(false);
const editing = ref<EventCategory | null>(null);
const pendingDeletion = ref<EventCategory | null>(null);
const isDeleting = ref(false);

const form = reactive({
  name: '',
  description: '',
  color: '#640c0e',
  sort_order: '0',
  is_active: true,
});

const deleteMessage = computed(() =>
  pendingDeletion.value
    ? `Delete "${pendingDeletion.value.name}"? Categories that still have events attached cannot be deleted.`
    : ''
);

onMounted(() => load());

async function load() {
  try {
    await store.fetchAll();
  } catch {
    // The store records the message; the error state renders it.
  }
}

const openCreate = () => {
  clear();
  editing.value = null;
  Object.assign(form, {
    name: '',
    description: '',
    color: '#640c0e',
    sort_order: String(store.categories.length),
    is_active: true,
  });
  isFormOpen.value = true;
};

const openEdit = (category: EventCategory) => {
  clear();
  editing.value = category;
  Object.assign(form, {
    name: category.name,
    description: category.description ?? '',
    color: category.color ?? '#640c0e',
    sort_order: String(category.sort_order),
    is_active: category.is_active,
  });
  isFormOpen.value = true;
};

const submit = async () => {
  clear();

  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    color: form.color || null,
    sort_order: Number(form.sort_order) || 0,
    is_active: form.is_active,
  };

  try {
    if (editing.value) {
      await store.update(editing.value.uuid, payload);
      toast.success('Category updated.');
    } else {
      await store.create(payload);
      toast.success('Category created.');
    }

    isFormOpen.value = false;
  } catch (caught) {
    handle(caught);
  }
};

/** Quick toggle from the list, without opening the form. */
const toggleActive = async (category: EventCategory) => {
  try {
    await store.update(category.uuid, { is_active: !category.is_active });
    toast.success(`"${category.name}" is now ${category.is_active ? 'inactive' : 'active'}.`);
  } catch (caught) {
    handle(caught);
  }
};

const confirmDelete = async () => {
  if (!pendingDeletion.value) return;

  isDeleting.value = true;

  try {
    await store.remove(pendingDeletion.value.uuid);
    toast.success('Category deleted.');
    pendingDeletion.value = null;
  } catch (caught) {
    // A 409 means events still reference it — the API message says so.
    handle(caught);
  } finally {
    isDeleting.value = false;
  }
};
</script>

<template>
  <div>
    <EmsPageHeader
      title="Event Categories"
      description="The taxonomy used to classify MSA events."
    >
      <template #actions>
        <Button v-if="canCreate" variant="primary" @click="openCreate">
          <template #left-icon><Plus class="h-4 w-4" /></template>
          New Category
        </Button>
      </template>
    </EmsPageHeader>

    <EmsErrorState v-if="store.error" :message="store.error" @retry="load" />

    <section v-else class="overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft">
      <EmsTableSkeleton v-if="store.isLoading" :columns="4" />

      <EmptyState
        v-else-if="store.categories.length === 0"
        class="my-8 border-0"
        title="No categories yet"
        description="Categories let organizers classify events for filtering and reporting."
        :action-label="canCreate ? 'New Category' : undefined"
        @action="openCreate"
      />

      <ul v-else class="divide-y divide-neutral-ivory">
        <li
          v-for="category in store.categories"
          :key="category.uuid"
          class="flex flex-wrap items-center gap-3 px-5 py-4"
        >
          <span
            class="h-3 w-3 shrink-0 rounded-full border border-black/5"
            :style="{ backgroundColor: category.color ?? '#c2c4c7' }"
            aria-hidden="true"
          />

          <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 text-sm font-bold text-neutral-black">
              {{ category.name }}
              <span
                v-if="!category.is_active"
                class="rounded-full bg-neutral-ivory px-2 py-0.5 text-[9px] font-bold uppercase text-neutral-muted"
              >
                Inactive
              </span>
            </p>
            <p class="truncate text-xs text-neutral-muted">
              {{ category.description || 'No description' }}
            </p>
          </div>

          <p class="hidden text-xs text-neutral-muted sm:block">
            {{ category.events_count ?? 0 }} events
          </p>

          <div class="flex items-center gap-1">
            <Button v-if="canUpdate" variant="ghost" size="sm" @click="toggleActive(category)">
              {{ category.is_active ? 'Deactivate' : 'Activate' }}
            </Button>
            <Button v-if="canUpdate" variant="ghost" size="sm" @click="openEdit(category)">
              Edit
            </Button>
            <Button v-if="canDelete" variant="ghost" size="sm" @click="pendingDeletion = category">
              Delete
            </Button>
          </div>
        </li>
      </ul>
    </section>

    <!-- Create / edit -->
    <Dialog
      :is-open="isFormOpen"
      :title="editing ? 'Edit category' : 'New category'"
      size="md"
      @close="isFormOpen = false"
    >
      <form id="ems-category-form" @submit.prevent="submit">
        <p
          v-if="generalError"
          class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-secondary"
          role="alert"
        >
          {{ generalError }}
        </p>

        <fieldset :disabled="store.isSaving">
          <legend class="sr-only">Category details</legend>

          <Input
            v-model="form.name"
            label="Name"
            placeholder="Community"
            required
            :error="fieldError('name')"
          />

          <Textarea
            v-model="form.description"
            label="Description"
            :rows="3"
            placeholder="What kind of events belong in this category?"
            :error="fieldError('description')"
          />

          <div class="mb-6 grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <label
                for="ems-category-color"
                class="block text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted"
              >
                Colour
              </label>
              <input
                id="ems-category-color"
                v-model="form.color"
                type="color"
                class="h-11 w-full cursor-pointer rounded-xl border border-neutral-ivory bg-white p-1"
              />
              <p v-if="fieldError('color')" class="text-[10.5px] font-semibold text-secondary">
                {{ fieldError('color') }}
              </p>
            </div>

            <Input
              v-model="form.sort_order"
              type="number"
              label="Sort order"
              :error="fieldError('sort_order')"
            />
          </div>

          <Switch
            v-model="form.is_active"
            label="Active"
            description="Only active categories can be assigned to new events."
          />
        </fieldset>
      </form>

      <template #footer>
        <Button variant="ghost" :disabled="store.isSaving" @click="isFormOpen = false">
          Cancel
        </Button>
        <Button variant="primary" :is-loading="store.isSaving" @click="submit">
          {{ editing ? 'Save changes' : 'Create category' }}
        </Button>
      </template>
    </Dialog>

    <EmsConfirmDialog
      :is-open="pendingDeletion !== null"
      title="Delete category"
      :message="deleteMessage"
      confirm-label="Delete"
      is-destructive
      :is-busy="isDeleting"
      @confirm="confirmDelete"
      @cancel="pendingDeletion = null"
    />
  </div>
</template>
