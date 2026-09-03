<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Plus, Star } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useToastStore } from '@/components/feedback/toast';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';
import {
  EmsCategoryBadge,
  EmsConfirmDialog,
  EmsErrorState,
  EmsPageHeader,
  EmsStatusBadge,
  EmsTableSkeleton,
} from '@/components/ems';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsCategoriesStore } from '@/stores/ems/emsCategories';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { Event, EventStatus } from '@/types/ems';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const events = useEmsEventsStore();
const categories = useEmsCategoriesStore();
const { canCreateEvents, canUpdateEvents, canDeleteEvents } = useEmsPermissions();
const { handle } = useEmsApiError();
const { formatDateRange, formatTimeRange } = useEventFormatting();

const search = ref((route.query.search as string) ?? '');
const pendingDeletion = ref<Event | null>(null);
const isDeleting = ref(false);

// The Select renders its own disabled placeholder for the empty value, so
// "any" is a named sentinel rather than ''.
const ANY = 'any';

const statusOptions = computed(() => [
  { label: 'All statuses', value: ANY },
  ...(events.lifecycle?.states ?? []).map((state) => ({ label: state.label, value: state.value })),
]);

const categoryOptions = computed(() => [
  { label: 'All categories', value: ANY },
  ...categories.activeCategories.map((category) => ({ label: category.name, value: String(category.id) })),
]);

const sortOptions = [
  { label: 'Start date', value: 'start_at' },
  { label: 'Name', value: 'name' },
  { label: 'Status', value: 'status' },
  { label: 'Recently created', value: 'created_at' },
];

const rangeLabel = computed(() => {
  const { from, to, total } = events.pagination;

  return total === 0 ? 'No results' : `Showing ${from ?? 0}–${to ?? 0} of ${total}`;
});

onMounted(async () => {
  void events.fetchLifecycle();
  void categories.ensureLoaded();

  await load({
    // Deep links from the dashboard summary cards arrive as query strings.
    status: (route.query.status as EventStatus) ?? '',
    upcoming: route.query.upcoming === '1' ? true : undefined,
    search: search.value,
    page: 1,
  });
});

async function load(overrides: Parameters<typeof events.fetchList>[0] = {}) {
  try {
    await events.fetchList(overrides);
  } catch {
    // fetchList records the message on the store; the error state renders it.
  }
}

/** Debounced so typing does not fire a request per keystroke. */
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load({ search: value, page: 1 }), 300);
});

const setStatus = (value: string | number) =>
  load({ status: value === ANY ? '' : (value as EventStatus), page: 1 });

const setCategory = (value: string | number) =>
  load({ category_id: value === ANY ? null : Number(value), page: 1 });

const setSort = (value: string | number) =>
  load({ sort_by: value as 'start_at' | 'name' | 'status' | 'created_at', page: 1 });

const openEvent = (event: Event) =>
  router.push({ name: 'ems-event-detail', params: { uuid: event.uuid } });

const deleteMessage = computed(() =>
  pendingDeletion.value
    ? `Delete "${pendingDeletion.value.name}"? The event is soft deleted and can be restored by an administrator.`
    : ''
);

const confirmDelete = async () => {
  if (!pendingDeletion.value) return;

  isDeleting.value = true;

  try {
    await events.remove(pendingDeletion.value.uuid);
    toast.success(`"${pendingDeletion.value.name}" was deleted.`);
    pendingDeletion.value = null;
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    isDeleting.value = false;
  }
};
</script>

<template>
  <div>
    <EmsPageHeader title="Events" description="Create, manage and progress MSA events.">
      <template #actions>
        <Button
          v-if="canCreateEvents"
          variant="primary"
          @click="router.push({ name: 'ems-event-create' })"
        >
          <template #left-icon><Plus class="h-4 w-4" /></template>
          Create Event
        </Button>
      </template>
    </EmsPageHeader>

    <!-- Filters -->
    <section
      class="mb-4 grid gap-3 rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft sm:grid-cols-2 lg:grid-cols-4"
      aria-label="Filter events"
    >
      <Input v-model="search" type="search" label="Search" placeholder="Event name or location" />
      <Select
        :model-value="events.filters.status || ANY"
        label="Status"
        :options="statusOptions"
        @update:model-value="setStatus"
      />
      <Select
        :model-value="events.filters.category_id ? String(events.filters.category_id) : ANY"
        label="Category"
        :options="categoryOptions"
        @update:model-value="setCategory"
      />
      <Select
        :model-value="events.filters.sort_by ?? 'start_at'"
        label="Sort by"
        :options="sortOptions"
        @update:model-value="setSort"
      />
    </section>

    <EmsErrorState v-if="events.listError" :message="events.listError" @retry="load()" />

    <section v-else class="overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft">
      <EmsTableSkeleton v-if="events.isLoadingList" :columns="5" />

      <EmptyState
        v-else-if="events.isEmpty"
        class="my-8 border-0"
        title="No events found"
        description="Adjust your filters, or create the first event."
        :action-label="canCreateEvents ? 'Create Event' : undefined"
        @action="router.push({ name: 'ems-event-create' })"
      />

      <template v-else>
        <!-- Desktop table -->
        <table class="hidden w-full text-left md:table">
          <caption class="sr-only">Events matching the current filters</caption>
          <thead class="border-b border-neutral-ivory bg-neutral-background/50">
            <tr class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">
              <th scope="col" class="px-5 py-3">Event</th>
              <th scope="col" class="px-5 py-3">Date &amp; Time</th>
              <th scope="col" class="px-5 py-3">Location</th>
              <th scope="col" class="px-5 py-3">Organizer</th>
              <th scope="col" class="px-5 py-3">Status</th>
              <th scope="col" class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr
              v-for="event in events.events"
              :key="event.uuid"
              class="cursor-pointer transition-colors hover:bg-neutral-background/50"
              @click="openEvent(event)"
            >
              <td class="px-5 py-4">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-bold text-neutral-black">{{ event.name }}</p>
                  <span v-if="event.is_featured" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-800" title="Featured Event">
                    <Star class="h-3 w-3 fill-amber-500 text-amber-500" />
                    Featured
                  </span>
                </div>
                <p v-if="event.category" class="mt-1">
                  <EmsCategoryBadge :name="event.category.name" :color="event.category.color" />
                </p>
              </td>
              <td class="px-5 py-4 text-xs text-neutral-muted">
                <p>{{ formatDateRange(event.start_at, event.end_at) }}</p>
                <p class="text-[11px]">{{ formatTimeRange(event.start_at, event.end_at) }}</p>
              </td>
              <td class="max-w-[200px] truncate px-5 py-4 text-xs text-neutral-muted">
                {{ event.location || '—' }}
              </td>
              <td class="px-5 py-4 text-xs text-neutral-muted">
                {{ event.organizer_name || event.organizer?.name || '—' }}
              </td>
              <td class="px-5 py-4">
                <EmsStatusBadge :label="event.status_label" :tone="event.status_tone" size="sm" />
              </td>
              <td class="px-5 py-4 text-right" @click.stop>
                <div class="flex justify-end gap-1">
                  <Button
                    v-if="canUpdateEvents"
                    variant="ghost"
                    size="sm"
                    @click="router.push({ name: 'ems-event-edit', params: { uuid: event.uuid } })"
                  >
                    Edit
                  </Button>
                  <Button
                    v-if="canDeleteEvents"
                    variant="ghost"
                    size="sm"
                    @click="pendingDeletion = event"
                  >
                    Delete
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Mobile cards -->
        <ul class="divide-y divide-neutral-ivory md:hidden">
          <li v-for="event in events.events" :key="event.uuid" class="p-4">
            <button type="button" class="w-full text-left cursor-pointer" @click="openEvent(event)">
              <div class="flex items-start justify-between gap-2">
                <div class="flex flex-wrap items-center gap-1.5 min-w-0 flex-1">
                  <p class="text-sm font-bold text-neutral-black">{{ event.name }}</p>
                  <span v-if="event.is_featured" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-800">
                    <Star class="h-3 w-3 fill-amber-500 text-amber-500" />
                    Featured
                  </span>
                </div>
                <EmsStatusBadge :label="event.status_label" :tone="event.status_tone" size="sm" />
              </div>
              <p v-if="event.category" class="mt-1">
                <EmsCategoryBadge :name="event.category.name" :color="event.category.color" />
              </p>
              <p class="mt-1 text-xs text-neutral-muted">
                {{ formatDateRange(event.start_at, event.end_at) }} · {{ formatTimeRange(event.start_at, event.end_at) }}
              </p>
              <p class="text-xs text-neutral-muted">{{ event.location || 'Location to be confirmed' }}</p>
            </button>
          </li>
        </ul>

        <!-- Pagination -->
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-ivory px-5 py-3"
        >
          <p class="text-xs text-neutral-muted">{{ rangeLabel }}</p>

          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              :disabled="events.pagination.current_page <= 1"
              @click="events.goToPage(events.pagination.current_page - 1)"
            >
              Previous
            </Button>
            <span class="text-xs text-neutral-muted">
              Page {{ events.pagination.current_page }} of {{ events.pagination.last_page }}
            </span>
            <Button
              variant="outline"
              size="sm"
              :disabled="events.pagination.current_page >= events.pagination.last_page"
              @click="events.goToPage(events.pagination.current_page + 1)"
            >
              Next
            </Button>
          </div>
        </div>
      </template>
    </section>

    <EmsConfirmDialog
      :is-open="pendingDeletion !== null"
      title="Delete event"
      :message="deleteMessage"
      confirm-label="Delete"
      is-destructive
      :is-busy="isDeleting"
      @confirm="confirmDelete"
      @cancel="pendingDeletion = null"
    />
  </div>
</template>
