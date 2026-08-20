<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useEmsDashboardStore } from '@/stores/ems/emsDashboard';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsCategoryBadge, EmsErrorState, EmsPageHeader, EmsStatusBadge, EmsSummaryCard } from '@/components/ems';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';

/**
 * The EMS overview.
 *
 * Every figure here is already scoped by the backend to what the viewer may
 * see, and the quick actions arrive pre-filtered by permission, so this page
 * renders what it is given rather than deciding who sees what.
 */
const dashboard = useEmsDashboardStore();
const { formatDateRange, formatTimeRange, formatRelative } = useEventFormatting();

onMounted(() => dashboard.fetch());

const cards = computed(() => {
  const summary = dashboard.summary;

  return [
    { label: 'Total Events', value: summary?.total ?? 0, accent: 'default' as const, to: '/ems/events' },
    { label: 'Draft', value: summary?.draft ?? 0, accent: 'draft' as const, to: '/ems/events?status=draft' },
    {
      label: 'Published',
      value: summary?.published ?? 0,
      accent: 'published' as const,
      to: '/ems/events?status=published',
    },
    { label: 'Upcoming', value: summary?.upcoming ?? 0, accent: 'upcoming' as const, to: '/ems/events?upcoming=1' },
    {
      label: 'Completed',
      value: summary?.completed ?? 0,
      accent: 'completed' as const,
      to: '/ems/events?status=completed',
    },
  ];
});
</script>

<template>
  <div>
    <EmsPageHeader
      title="Event Management"
      description="An overview of the events you are responsible for."
    >
      <template #actions>
        <RouterLink
          v-for="action in dashboard.quickActions"
          :key="action.key"
          :to="action.route"
          class="inline-flex h-9 items-center rounded-md border border-neutral-ivory bg-white px-4 text-sm font-bold text-primary transition-colors hover:bg-neutral-background first:border-primary first:bg-primary first:text-white first:hover:bg-primary/95"
        >
          {{ action.label }}
        </RouterLink>
      </template>
    </EmsPageHeader>

    <EmsErrorState
      v-if="dashboard.error"
      :message="dashboard.error"
      @retry="dashboard.fetch()"
    />

    <template v-else>
      <!-- Summary cards -->
      <section aria-label="Event summary" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        <EmsSummaryCard
          v-for="card in cards"
          :key="card.label"
          :label="card.label"
          :value="card.value"
          :accent="card.accent"
          :to="card.to"
          :is-loading="dashboard.isLoading"
        />
      </section>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Upcoming events -->
        <section
          class="rounded-2xl border border-neutral-ivory bg-white shadow-soft lg:col-span-2"
          aria-labelledby="ems-upcoming-heading"
        >
          <div class="flex items-center justify-between border-b border-neutral-ivory px-5 py-4">
            <h2 id="ems-upcoming-heading" class="text-sm font-bold text-neutral-black">
              Upcoming Events
            </h2>
            <RouterLink
              :to="{ name: 'ems-events' }"
              class="text-xs font-semibold text-primary hover:underline"
            >
              View all
            </RouterLink>
          </div>

          <div v-if="dashboard.isLoading" class="divide-y divide-neutral-ivory" role="status">
            <div v-for="row in 3" :key="row" class="space-y-2 px-5 py-4">
              <div class="h-3 w-1/3 animate-pulse rounded-full bg-neutral-ivory/70" />
              <div class="h-3 w-2/3 animate-pulse rounded-full bg-neutral-ivory/50" />
            </div>
          </div>

          <ul v-else-if="dashboard.upcomingEvents.length" class="divide-y divide-neutral-ivory">
            <li v-for="event in dashboard.upcomingEvents" :key="event.uuid">
              <RouterLink
                :to="{ name: 'ems-event-detail', params: { uuid: event.uuid } }"
                class="block px-5 py-4 transition-colors hover:bg-neutral-background/60"
              >
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <p class="min-w-0 flex-1 truncate text-sm font-bold text-neutral-black">
                    {{ event.name }}
                  </p>
                  <EmsStatusBadge :label="event.status_label" :tone="event.status_tone" size="sm" />
                </div>

                <dl class="mt-2 grid gap-x-6 gap-y-1 text-xs text-neutral-muted sm:grid-cols-2">
                  <div class="flex gap-1.5">
                    <dt class="sr-only">Date and time</dt>
                    <dd>{{ formatDateRange(event.start_at, event.end_at) }} · {{ formatTimeRange(event.start_at, event.end_at) }}</dd>
                  </div>
                  <div class="flex gap-1.5">
                    <dt class="sr-only">Location</dt>
                    <dd class="truncate">{{ event.location || 'Location to be confirmed' }}</dd>
                  </div>
                  <div class="flex gap-1.5">
                    <dt class="sr-only">Organizer</dt>
                    <dd class="truncate">
                      {{ event.organizer_name || event.organizer?.name || 'No organizer assigned' }}
                    </dd>
                  </div>
                  <div v-if="event.category" class="flex gap-1.5">
                    <dt class="sr-only">Category</dt>
                    <dd>
                      <EmsCategoryBadge :name="event.category.name" :color="event.category.color" />
                    </dd>
                  </div>
                </dl>
              </RouterLink>
            </li>
          </ul>

          <EmptyState
            v-else
            class="my-6 border-0"
            title="No upcoming events"
            description="Events with a future start date will appear here."
          />
        </section>

        <!-- Recent activity -->
        <section
          class="rounded-2xl border border-neutral-ivory bg-white shadow-soft"
          aria-labelledby="ems-activity-heading"
        >
          <div class="border-b border-neutral-ivory px-5 py-4">
            <h2 id="ems-activity-heading" class="text-sm font-bold text-neutral-black">
              Recent Activity
            </h2>
          </div>

          <div v-if="dashboard.isLoading" class="space-y-3 px-5 py-4" role="status">
            <div v-for="row in 4" :key="row" class="h-3 animate-pulse rounded-full bg-neutral-ivory/70" />
          </div>

          <ol v-else-if="dashboard.recentActivity.length" class="divide-y divide-neutral-ivory">
            <li v-for="entry in dashboard.recentActivity" :key="entry.id" class="px-5 py-3">
              <p class="text-xs text-neutral-black">
                {{ entry.description ?? entry.action }}
              </p>
              <p class="mt-1 flex items-center gap-2 text-[11px] text-neutral-muted">
                <span>{{ entry.actor?.name ?? 'System' }}</span>
                <span aria-hidden="true">·</span>
                <time :datetime="entry.created_at ?? undefined">
                  {{ formatRelative(entry.created_at) }}
                </time>
                <span
                  v-if="entry.result !== 'success'"
                  class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-amber-800"
                >
                  {{ entry.result }}
                </span>
              </p>
            </li>
          </ol>

          <p v-else class="px-5 py-8 text-center text-xs text-neutral-muted">
            No recorded activity yet.
          </p>
        </section>
      </div>
    </template>
  </div>
</template>
