<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { templatesService } from '@/services/ems/templatesService';
import { useEmsTemplatesStore } from '@/stores/ems/emsTemplates';
import { useEmsCategoriesStore } from '@/stores/ems/emsCategories';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useToastStore } from '@/components/feedback/toast';
import { Plus, Trash2, Edit2, Copy } from 'lucide-vue-next';
import type { EventTemplate } from '@/types/ems';

const toast = useToastStore();
const { fieldError, generalError, handle, clear } = useEmsApiError();

const templatesStore = useEmsTemplatesStore();
const categoriesStore = useEmsCategoriesStore();

const isLoading = computed(() => templatesStore.isLoading);
const error = computed(() => templatesStore.error);
const templates = computed(() => templatesStore.templates);

// Form / Modal state
const isFormOpen = ref(false);
const isSaving = ref(false);
const editingTemplate = ref<EventTemplate | null>(null);

const UNCATEGORISED = 'none';

const form = ref({
  name: '',
  description: '',
  category_id: UNCATEGORISED as string,
  capacity: '' as number | '',
  is_public: false,
  waitlist_enabled: false,
  max_tickets_per_order: '' as number | '',
  max_registrations_per_attendee: '' as number | '',
  registration_deadline_offset_days: '' as number | '',
  is_default: false,
});

const categoryOptions = computed(() => [
  { label: 'Uncategorised', value: UNCATEGORISED },
  ...categoriesStore.activeCategories.map((category) => ({
    label: category.name,
    value: String(category.id),
  })),
]);

const loadData = async () => {
  try {
    await Promise.all([
      templatesStore.fetchAll(),
      categoriesStore.ensureLoaded(),
    ]);
  } catch (caught) {
    handle(caught);
  }
};

onMounted(loadData);

const openCreate = () => {
  editingTemplate.value = null;
  form.value = {
    name: '',
    description: '',
    category_id: UNCATEGORISED,
    capacity: '',
    is_public: false,
    waitlist_enabled: false,
    max_tickets_per_order: '',
    max_registrations_per_attendee: '',
    registration_deadline_offset_days: '',
    is_default: false,
  };
  clear();
  isFormOpen.value = true;
};

const openEdit = (template: EventTemplate) => {
  editingTemplate.value = template;
  form.value = {
    name: template.name,
    description: template.description || '',
    category_id: template.category_id ? String(template.category_id) : UNCATEGORISED,
    capacity: template.capacity || '',
    is_public: template.is_public,
    waitlist_enabled: template.waitlist_enabled,
    max_tickets_per_order: template.max_tickets_per_order || '',
    max_registrations_per_attendee: template.max_registrations_per_attendee || '',
    registration_deadline_offset_days: template.registration_deadline_offset_days || '',
    is_default: template.is_default,
  };
  clear();
  isFormOpen.value = true;
};

const save = async () => {
  clear();
  isSaving.value = true;
  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description.trim() || null,
      category_id: form.value.category_id === UNCATEGORISED ? null : Number(form.value.category_id),
      capacity: form.value.capacity ? Number(form.value.capacity) : null,
      is_public: form.value.is_public,
      waitlist_enabled: form.value.waitlist_enabled,
      max_tickets_per_order: form.value.max_tickets_per_order ? Number(form.value.max_tickets_per_order) : null,
      max_registrations_per_attendee: form.value.max_registrations_per_attendee ? Number(form.value.max_registrations_per_attendee) : null,
      registration_deadline_offset_days: form.value.registration_deadline_offset_days ? Number(form.value.registration_deadline_offset_days) : null,
      is_default: form.value.is_default,
    };

    if (editingTemplate.value) {
      await templatesStore.update(editingTemplate.value.uuid, payload);
      toast.success('Template updated successfully.');
    } else {
      await templatesStore.create(payload);
      toast.success('Template created successfully.');
    }
    isFormOpen.value = false;
    await templatesStore.fetchAll();
  } catch (caught) {
    handle(caught);
  } finally {
    isSaving.value = false;
  }
};

const duplicateTemplate = async (template: EventTemplate) => {
  try {
    isLoading.value; // Show loading if possible
    // Backend has a replica call: POST /ems/event-templates/{template}/duplicate
    // Let's call the replication endpoint directly via templatesService
    const duplicate = await templatesService.create({
      name: template.name + ' (Copy)',
      description: template.description,
      category_id: template.category_id,
      capacity: template.capacity,
      is_public: template.is_public,
      waitlist_enabled: template.waitlist_enabled,
      max_tickets_per_order: template.max_tickets_per_order,
      max_registrations_per_attendee: template.max_registrations_per_attendee,
      registration_deadline_offset_days: template.registration_deadline_offset_days,
      is_default: false,
    });
    toast.success(`Duplicated: ${duplicate.name}`);
    await templatesStore.fetchAll();
  } catch (caught) {
    handle(caught);
  }
};

const remove = async (template: EventTemplate) => {
  if (!confirm(`Are you sure you want to delete template ${template.name}?`)) return;
  try {
    await templatesStore.remove(template.uuid);
    toast.success('Template deleted.');
  } catch (caught) {
    handle(caught);
  }
};
</script>

<template>
  <div class="space-y-6">
    <EmsPageHeader
      title="Event Templates"
      description="Manage reusable event configurations and default lifecycle options."
      back-to="/ems"
      back-label="Dashboard"
    >
      <template #actions>
        <Button @click="openCreate">
          <template #left-icon><Plus class="h-4 w-4" /></template>
          Create Template
        </Button>
      </template>
    </EmsPageHeader>

    <div v-if="isLoading" class="h-48 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    <EmsErrorState v-else-if="error" title="Unable to load templates" :message="error" can-retry @retry="loadData" />

    <div v-else class="overflow-x-auto rounded-2xl border border-neutral-ivory bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted bg-neutral-background/40">
          <tr>
            <th class="px-5 py-4">Name</th>
            <th class="px-5 py-4">Category</th>
            <th class="px-5 py-4">Capacity</th>
            <th class="px-5 py-4">Options</th>
            <th class="px-5 py-4">Deadline Offset</th>
            <th class="px-5 py-4">Default</th>
            <th class="px-5 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory/70">
          <tr v-for="tpl in templates" :key="tpl.uuid" class="hover:bg-neutral-background/30 transition-colors">
            <td class="px-5 py-4">
              <div class="font-semibold text-neutral-black">{{ tpl.name }}</div>
              <div class="text-xs text-neutral-muted truncate max-w-xs">{{ tpl.description || 'No description' }}</div>
            </td>
            <td class="px-5 py-4 text-neutral-muted">
              {{ tpl.category?.name || 'Uncategorised' }}
            </td>
            <td class="px-5 py-4 font-medium text-neutral-black">
              {{ tpl.capacity || 'Unlimited' }}
            </td>
            <td class="px-5 py-4 text-neutral-muted text-xs space-y-0.5">
              <div class="flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full" :class="tpl.is_public ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                <span>{{ tpl.is_public ? 'Publicly discoverable' : 'Private' }}</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full" :class="tpl.waitlist_enabled ? 'bg-purple-500' : 'bg-gray-400'"></span>
                <span>Waitlist: {{ tpl.waitlist_enabled ? 'Enabled' : 'Disabled' }}</span>
              </div>
            </td>
            <td class="px-5 py-4 text-neutral-black">
              {{ tpl.registration_deadline_offset_days ? `${tpl.registration_deadline_offset_days} days before` : 'None' }}
            </td>
            <td class="px-5 py-4">
              <span v-if="tpl.is_default" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-semibold">
                Default
              </span>
              <span v-else class="text-xs text-neutral-muted">—</span>
            </td>
            <td class="px-5 py-4 text-right space-x-1.5">
              <Button size="sm" variant="ghost" class="p-1.5" title="Edit" @click="openEdit(tpl)">
                <Edit2 class="h-3.5 w-3.5" />
              </Button>
              <Button size="sm" variant="ghost" class="p-1.5" title="Duplicate" @click="duplicateTemplate(tpl)">
                <Copy class="h-3.5 w-3.5" />
              </Button>
              <Button size="sm" variant="ghost" class="p-1.5 text-secondary hover:text-red-700" title="Delete" @click="remove(tpl)">
                <Trash2 class="h-3.5 w-3.5" />
              </Button>
            </td>
          </tr>
          <tr v-if="templates.length === 0">
            <td colspan="7" class="p-8 text-center text-neutral-muted text-sm">
              No event templates found. Click "Create Template" to create one.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Slide-over/Modal -->
    <div
      v-if="isFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-end bg-black/40 backdrop-blur-xs p-4"
      @click.self="isFormOpen = false"
    >
      <div class="h-full w-full max-w-lg rounded-2xl bg-white shadow-premium flex flex-col animate-slide-left overflow-y-auto">
        <div class="px-6 py-5 border-b border-neutral-ivory flex items-center justify-between">
          <h3 class="text-lg font-bold text-primary">{{ editingTemplate ? 'Edit Template' : 'Create Template' }}</h3>
          <button type="button" @click="isFormOpen = false" class="text-neutral-muted hover:text-neutral-black">&times;</button>
        </div>

        <form class="flex-1 p-6 space-y-4" @submit.prevent="save">
          <p v-if="generalError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-secondary">
            {{ generalError }}
          </p>

          <Input
            v-model="form.name"
            label="Template Name"
            placeholder="Regular Weekly Halaqah"
            required
            :error="fieldError('name')"
          />

          <Input
            v-model="form.description"
            label="Description"
            placeholder="Standard configuration for halaqah study circles."
            :error="fieldError('description')"
          />

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Category</label>
              <select
                v-model="form.category_id"
                class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
              >
                <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <Input
              v-model="form.capacity"
              type="number"
              label="Default Capacity"
              placeholder="e.g. 50 (optional)"
              :error="fieldError('capacity')"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <Input
              v-model="form.max_tickets_per_order"
              type="number"
              label="Max Tickets/Order"
              placeholder="e.g. 5"
              :error="fieldError('max_tickets_per_order')"
            />
            <Input
              v-model="form.max_registrations_per_attendee"
              type="number"
              label="Max Registrations/Attendee"
              placeholder="e.g. 1"
              :error="fieldError('max_registrations_per_attendee')"
            />
          </div>

          <Input
            v-model="form.registration_deadline_offset_days"
            type="number"
            label="Registration Deadline Offset (Days before event)"
            placeholder="e.g. 2 (optional)"
            :error="fieldError('registration_deadline_offset_days')"
          />

          <div class="space-y-3 py-2 border-t border-neutral-ivory">
            <Switch
              v-model="form.is_public"
              label="Publicly Listable by Default"
              description="Whether new events are eligible for public search listings."
            />
            <Switch
              v-model="form.waitlist_enabled"
              label="Enable Waitlist"
              description="Backfill registrations dynamically when ticket allocations fill."
            />
            <Switch
              v-model="form.is_default"
              label="Default System Template"
              description="Use as the starting template when creating new events."
            />
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-neutral-ivory">
            <Button variant="ghost" type="button" @click="isFormOpen = false">Cancel</Button>
            <Button variant="primary" type="submit" :is-loading="isSaving">
              {{ editingTemplate ? 'Save Changes' : 'Create Template' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
