<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';
import {
  EmsConfirmDialog,
  EmsErrorState,
  EmsPageHeader,
  EventLifecyclePanel,
} from '@/components/ems';
import TicketManagementPanel from '@/components/ems/TicketManagementPanel.vue';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EventTransitionAction } from '@/types/ems';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const events = useEmsEventsStore();
const { canUpdateEvents, canDeleteEvents, canViewOperations, canCheckIn, canViewAttendees, canImportAttendees, canViewNotifications, canViewAnalytics } =
  useEmsPermissions();
const { handle } = useEmsApiError();
const { formatDateTime } = useEventFormatting();

const isDeleteOpen = ref(false);
const isDeleting = ref(false);

const uuid = computed(() => route.params.uuid as string);
const event = computed(() => events.current);

const load = async () => {
  try {
    await events.fetchOne(uuid.value);
  } catch {
    // fetchOne stores the error; the states below render it.
  }
};

onMounted(load);
watch(uuid, load);

/** Timestamps worth surfacing, hidden until the lifecycle sets them. */
const lifecycleTimestamps = computed(() => {
  const current = event.value;
  if (!current) return [];

  return [
    { label: 'Published', value: current.published_at },
    { label: 'Registration opened', value: current.registration_open_at },
    { label: 'Registration closed', value: current.registration_closed_at },
    { label: 'Completed', value: current.completed_at },
    { label: 'Archived', value: current.archived_at },
    { label: 'Cancelled', value: current.cancelled_at ?? null },
  ].filter((entry) => entry.value !== null);
});

const applyTransition = async (action: EventTransitionAction) => {
  try {
    const updated = await events.transition(uuid.value, action);
    toast.success(`Event is now ${updated.status_label}.`);
  } catch (caught) {
    // A 409 means the state moved underneath us; reload to show the truth.
    const error = handle(caught);

    if (error.isConflict) {
      await load();
    }
  }
};

const confirmDelete = async () => {
  isDeleting.value = true;

  try {
    await events.remove(uuid.value);
    toast.success('Event deleted.');
    await router.push({ name: 'ems-events' });
  } catch (caught) {
    handle(caught);
  } finally {
    isDeleting.value = false;
    isDeleteOpen.value = false;
  }
};
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="events.isLoadingCurrent" class="space-y-4" role="status" aria-label="Loading event">
      <div class="h-8 w-1/3 animate-pulse rounded-lg bg-neutral-ivory/70" />
      <div class="grid gap-6 lg:grid-cols-3">
        <div class="h-64 animate-pulse rounded-2xl bg-neutral-ivory/50 lg:col-span-2" />
        <div class="h-40 animate-pulse rounded-2xl bg-neutral-ivory/50" />
      </div>
    </div>

    <!-- Not found / forbidden / failed -->
    <EmsErrorState
      v-else-if="events.currentError"
      :title="events.currentError.isNotFound ? 'Event not found' : 'Unable to load this event'"
      :message="events.currentError.message"
      :can-retry="!events.currentError.isNotFound && !events.currentError.isForbidden"
      @retry="load"
    />

    <template v-else-if="event">
      <EmsPageHeader
        :title="event.name"
        :description="event.short_description ?? undefined"
        back-to="/ems/events"
        back-label="All events"
      >
        <template #actions>
          <Button
            v-if="canViewAnalytics"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-analytics', params: { uuid } })"
          >
            Analytics
          </Button>
          <Button
            v-if="canViewOperations"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-operations', params: { uuid } })"
          >
            Operations
          </Button>
          <Button
            v-if="canViewNotifications"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-notifications', params: { uuid } })"
          >
            Communications
          </Button>
          <Button
            v-if="canViewAttendees"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-attendees', params: { uuid } })"
          >
            Attendees
          </Button>
          <Button
            v-if="canCheckIn"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-staff', params: { uuid } })"
          >
            Staff check-in
          </Button>
          <Button
            v-if="canImportAttendees"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-import', params: { uuid } })"
          >
            Import
          </Button>
          <Button
            v-if="canUpdateEvents"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-edit', params: { uuid } })"
          >
            Edit
          </Button>
          <Button
            v-if="canUpdateEvents"
            variant="outline"
            size="sm"
            @click="router.push({ name: 'ems-event-create', query: { duplicate_from: uuid } })"
          >
            Duplicate
          </Button>
          <Button v-if="canDeleteEvents" variant="ghost" size="sm" @click="isDeleteOpen = true">Delete</Button>
        </template>
      </EmsPageHeader>

      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Details -->
        <section
          class="space-y-6 rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft lg:col-span-2"
          aria-labelledby="ems-detail-heading"
        >
          <h2 id="ems-detail-heading" class="sr-only">Event details</h2>

          <dl class="grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Starts</dt>
              <dd class="mt-1 text-sm text-neutral-black">{{ formatDateTime(event.start_at) }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Ends</dt>
              <dd class="mt-1 text-sm text-neutral-black">{{ formatDateTime(event.end_at) }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Location</dt>
              <dd class="mt-1 text-sm text-neutral-black">{{ event.location || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Category</dt>
              <dd class="mt-1 text-sm text-neutral-black">{{ event.category?.name ?? 'Uncategorised' }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Organizer</dt>
              <dd class="mt-1 text-sm text-neutral-black">
                {{ event.organizer_name || event.organizer?.name || 'Not assigned' }}
              </dd>
            </div>
            <div>
              <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Capacity</dt>
              <dd class="mt-1 text-sm text-neutral-black">{{ event.capacity ?? 'Unlimited' }}</dd>
            </div>
          </dl>

          <div v-if="event.description">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Description</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-neutral-black">
              {{ event.description }}
            </p>
          </div>

          <div class="border-t border-neutral-ivory pt-4">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Reference</h3>
            <dl class="mt-2 grid gap-2 text-xs text-neutral-muted sm:grid-cols-2">
              <div class="flex gap-2">
                <dt>Slug</dt>
                <dd class="font-mono text-neutral-black">{{ event.slug }}</dd>
              </div>
              <div class="flex gap-2">
                <dt>Created by</dt>
                <dd class="text-neutral-black">{{ event.creator?.name ?? 'Unknown' }}</dd>
              </div>
            </dl>
          </div>
        </section>

        <!-- Lifecycle + timeline -->
        <div class="space-y-6">
          <EventLifecyclePanel
            :event="event"
            :is-busy="events.isTransitioning"
            @transition="applyTransition"
          />

          <section
            v-if="lifecycleTimestamps.length"
            class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"
            aria-labelledby="ems-timeline-heading"
          >
            <h2
              id="ems-timeline-heading"
              class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted"
            >
              Timeline
            </h2>
            <ol class="mt-3 space-y-3">
              <li v-for="entry in lifecycleTimestamps" :key="entry.label" class="text-xs">
                <p class="font-semibold text-neutral-black">{{ entry.label }}</p>
                <time class="text-neutral-muted" :datetime="entry.value ?? undefined">
                  {{ formatDateTime(entry.value) }}
                </time>
              </li>
            </ol>
          </section>
        </div>
      </div>

      <div class="mt-6">
        <TicketManagementPanel :event-uuid="uuid" />
      </div>

      <EmsConfirmDialog
        :is-open="isDeleteOpen"
        title="Delete event"
        :message="`Delete ${event.name}? The event is soft deleted and can be restored by an administrator.`"
        confirm-label="Delete"
        is-destructive
        :is-busy="isDeleting"
        @confirm="confirmDelete"
        @cancel="isDeleteOpen = false"
      />
    </template>
  </div>
</template>
