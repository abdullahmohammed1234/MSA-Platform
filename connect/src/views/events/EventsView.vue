<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Campus Life & Gatherings"
      title="Campus Events & Passes"
      description="Discover upcoming lectures, Friday Jummah prayers, study halaqahs, and campus socials. All students are welcome."
    >
      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :is-loading="eventsStore.isLoading"
          @click="refreshEvents"
          title="Refresh events list"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5 text-[#b02e32]" :class="{ 'animate-spin': eventsStore.isLoading }" />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Events and Passes Navigation" />

    <!-- Unified Filters & Search Command Bar -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-3">
      <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="eventsStore.searchQuery"
            type="text"
            placeholder="Search events by title or keyword..."
            class="w-full pl-10 pr-9 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/50 text-xs text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] transition-colors"
            @keyup.enter="handleSearchSubmit"
          />
          <button
            v-if="eventsStore.searchQuery"
            type="button"
            @click="clearSearch"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700 cursor-pointer"
            aria-label="Clear search"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Time Filter Segmented Control & Action Button -->
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
          <div class="inline-flex rounded-xl bg-[#fffbf4] border border-[#ebe8de] p-1 text-xs">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
              :class="eventsStore.timeFilter === 'upcoming' ? 'bg-[#640c0e] text-white shadow-xs font-semibold' : 'text-neutral-600 hover:text-[#640c0e]'"
              @click="setTimeFilter('upcoming')"
            >
              Upcoming
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
              :class="eventsStore.timeFilter === 'past' ? 'bg-[#640c0e] text-white shadow-xs font-semibold' : 'text-neutral-600 hover:text-[#640c0e]'"
              @click="setTimeFilter('past')"
            >
              Past
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
              :class="eventsStore.timeFilter === 'all' ? 'bg-[#640c0e] text-white shadow-xs font-semibold' : 'text-neutral-600 hover:text-[#640c0e]'"
              @click="setTimeFilter('all')"
            >
              All
            </button>
          </div>

          <Button variant="primary" size="sm" @click="handleSearchSubmit">
            <span>Filter</span>
          </Button>
        </div>
      </div>

      <!-- Category Filter Pills (if categories exist) -->
      <div v-if="eventsStore.categories.length > 0" class="flex items-center gap-1.5 overflow-x-auto pb-1 pt-0.5 scrollbar-none border-t border-[#ebe8de]/60 pt-2.5">
        <button
          type="button"
          class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition-colors shrink-0 cursor-pointer"
          :class="!eventsStore.selectedCategory ? 'bg-[#640c0e] text-[#ffdc83]' : 'bg-[#fffbf4] border border-[#ebe8de] text-neutral-600 hover:bg-[#ebe8de]/70'"
          @click="selectCategory('')"
        >
          All Categories
        </button>

        <button
          v-for="cat in eventsStore.categories"
          :key="cat.uuid"
          type="button"
          class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition-colors shrink-0 cursor-pointer"
          :class="eventsStore.selectedCategory === cat.slug ? 'bg-[#640c0e] text-[#ffdc83]' : 'bg-[#fffbf4] border border-[#ebe8de] text-neutral-600 hover:bg-[#ebe8de]/70'"
          @click="selectCategory(cat.slug)"
        >
          {{ cat.name }}
        </button>
      </div>
    </div>

    <!-- Error State -->
    <ErrorState
      v-if="eventsStore.error"
      title="Failed to Load Events"
      :message="eventsStore.error"
      @retry="refreshEvents"
    />

    <!-- Loading State -->
    <div
      v-else-if="eventsStore.isLoading"
      class="py-24 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Fetching campus events...</p>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="eventsStore.events.length === 0"
      title="No Events Found"
      description="There are currently no events matching your search or category filter criteria."
    >
      <template #icon>
        <CalendarX class="w-8 h-8 text-[#640c0e]" />
      </template>
      <template #action>
        <Button variant="outline" size="sm" @click="resetFilters">
          <span>Reset All Filters</span>
        </Button>
      </template>
    </EmptyState>

    <!-- Events Grid -->
    <div v-else class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <EventCard
          v-for="event in eventsStore.events"
          :key="event.uuid"
          :event="event"
        />
      </div>

      <!-- Pagination (if applicable) -->
      <div
        v-if="eventsStore.pagination && eventsStore.pagination.last_page > 1"
        class="pt-4 flex items-center justify-between border-t border-[#ebe8de]"
      >
        <span class="text-xs text-neutral-500">
          Showing page {{ eventsStore.pagination.current_page }} of {{ eventsStore.pagination.last_page }} ({{ eventsStore.pagination.total }} total events)
        </span>

        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="eventsStore.pagination.current_page <= 1"
            @click="changePage(eventsStore.pagination.current_page - 1)"
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="eventsStore.pagination.current_page >= eventsStore.pagination.last_page"
            @click="changePage(eventsStore.pagination.current_page + 1)"
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useEventsStore } from '@/stores/events';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import EventCard from '@/components/events/EventCard.vue';
import Button from '@/components/ui/Button.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import { Search, X, CalendarX, RefreshCw, Calendar, Ticket } from 'lucide-vue-next';

const eventsStore = useEventsStore();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/events',
    label: 'Browse Events',
    icon: Calendar,
  },
  {
    to: '/app/tickets',
    label: 'My Passes Wallet',
    icon: Ticket,
  },
]);

function handleSearchSubmit() {
  eventsStore.fetchEvents({ page: 1 });
}

function clearSearch() {
  eventsStore.searchQuery = '';
  eventsStore.fetchEvents({ page: 1 });
}

function setTimeFilter(filter: 'upcoming' | 'past' | 'all') {
  eventsStore.timeFilter = filter;
  eventsStore.fetchEvents({ page: 1 });
}

function selectCategory(categorySlug: string) {
  eventsStore.selectedCategory = categorySlug;
  eventsStore.fetchEvents({ page: 1 });
}

function resetFilters() {
  eventsStore.searchQuery = '';
  eventsStore.selectedCategory = '';
  eventsStore.timeFilter = 'upcoming';
  eventsStore.fetchEvents({ page: 1 });
}

function changePage(page: number) {
  eventsStore.fetchEvents({ page });
}

function refreshEvents() {
  eventsStore.fetchEvents();
}

onMounted(async () => {
  await Promise.all([
    eventsStore.fetchCategories(),
    eventsStore.fetchEvents(),
  ]);
});
</script>
