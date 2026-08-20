<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, List } from 'lucide-vue-next';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import { EMS_PUBLIC_EVENTS_PATH } from '@/constants/ems';
import type { PublicCalendarEvent, PublicCategory } from '@/types/ems/public';

useSeo({
  title: 'Event Calendar | SFU MSA',
  description: 'Monthly and weekly calendar of SFU MSA events.',
});

const router = useRouter();
const { categoryTintStyle, categorySolidStyle, eventLocalDateKeys, localDateKey } = useEventFormatting();

const view = ref<'month' | 'week'>('month');
const cursor = ref(startOfMonth(new Date()));
const events = ref<PublicCalendarEvent[]>([]);
const categories = ref<PublicCategory[]>([]);
const selectedCategory = ref('all');
const rangeFilter = ref<'all' | 'upcoming' | 'past'>('all');
const loading = ref(true);
const error = ref('');

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const titleLabel = computed(() => {
  if (view.value === 'month') {
    return cursor.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  }

  const start = startOfWeek(cursor.value);
  const end = addDays(start, 6);
  return `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

const monthCells = computed(() => {
  const first = startOfMonth(cursor.value);
  const start = startOfWeek(first);
  const cells: Array<{ date: Date; inMonth: boolean; key: string }> = [];

  for (let i = 0; i < 42; i++) {
    const date = addDays(start, i);
    cells.push({
      date,
      inMonth: date.getMonth() === first.getMonth(),
      key: dateKey(date),
    });
  }

  return cells;
});

const weekDays = computed(() => {
  const start = startOfWeek(cursor.value);
  return Array.from({ length: 7 }, (_, i) => addDays(start, i));
});

const eventsByDay = computed(() => {
  const map = new Map<string, PublicCalendarEvent[]>();

  for (const event of events.value) {
    for (const key of eventLocalDateKeys(event.start_at, event.end_at)) {
      const list = map.get(key) ?? [];
      list.push(event);
      map.set(key, list);
    }
  }

  return map;
});

async function loadCategories() {
  try {
    categories.value = await publicEventsService.listCategories();
  } catch {
    categories.value = [];
  }
}

async function loadEvents() {
  loading.value = true;
  error.value = '';

  try {
    const { startsAfter, startsBefore } = windowBounds();

    events.value = await publicEventsService.calendar({
      starts_after: startsAfter.toISOString(),
      starts_before: startsBefore.toISOString(),
      category_slug: selectedCategory.value === 'all' ? undefined : selectedCategory.value,
      upcoming: rangeFilter.value === 'upcoming' ? true : undefined,
      past: rangeFilter.value === 'past' ? true : undefined,
    });
  } catch (err) {
    error.value = err instanceof EmsApiError ? err.message : 'Unable to load calendar.';
    events.value = [];
  } finally {
    loading.value = false;
  }
}

function windowBounds() {
  if (view.value === 'month') {
    const first = startOfMonth(cursor.value);
    const start = startOfWeek(first);
    const end = addDays(start, 41);
    end.setHours(23, 59, 59, 999);
    return { startsAfter: start, startsBefore: end };
  }

  const start = startOfWeek(cursor.value);
  const end = addDays(start, 6);
  end.setHours(23, 59, 59, 999);
  return { startsAfter: start, startsBefore: end };
}

function shift(delta: number) {
  if (view.value === 'month') {
    cursor.value = addMonths(cursor.value, delta);
  } else {
    cursor.value = addDays(cursor.value, delta * 7);
  }
}

function goToday() {
  cursor.value = view.value === 'month' ? startOfMonth(new Date()) : new Date();
}

function openEvent(slug: string) {
  router.push({ name: 'ems-public-event', params: { slug } });
}

function eventsOn(date: Date): PublicCalendarEvent[] {
  return eventsByDay.value.get(dateKey(date)) ?? [];
}

function eventColorStyle(event: PublicCalendarEvent) {
  return categoryTintStyle(event.category?.color);
}

function isToday(date: Date): boolean {
  return dateKey(date) === dateKey(new Date());
}

watch([view, cursor, selectedCategory, rangeFilter], () => void loadEvents());

onMounted(async () => {
  await loadCategories();
  await loadEvents();
});

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function startOfWeek(date: Date): Date {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() - d.getDay());
  return d;
}

function addDays(date: Date, days: number): Date {
  const d = new Date(date);
  d.setDate(d.getDate() + days);
  return d;
}

function addMonths(date: Date, months: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + months, 1);
}

function dateKey(date: Date): string {
  return localDateKey(date);
}
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <section class="bg-primary pt-28 pb-12">
      <div class="container-custom">
        <p class="text-accent-gold text-[11px] font-extrabold uppercase tracking-[0.2em] mb-3">
          SFU MSA
        </p>
        <h1 class="font-display text-4xl sm:text-5xl font-black text-white">Event Calendar</h1>
        <p class="mt-3 text-white/70 max-w-xl">
          Browse events by month or week. Click any event to open its landing page.
        </p>
        <RouterLink
          :to="EMS_PUBLIC_EVENTS_PATH"
          class="mt-6 inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-white/80 hover:text-white"
        >
          <List :size="14" /> List view
        </RouterLink>
      </div>
    </section>

    <section class="container-custom pt-8 space-y-5">
      <div class="flex flex-col lg:flex-row gap-4 lg:items-center lg:justify-between">
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="p-2 rounded-xl border border-neutral-ivory bg-white cursor-pointer"
            aria-label="Previous"
            @click="shift(-1)"
          >
            <ChevronLeft :size="18" />
          </button>
          <button
            type="button"
            class="p-2 rounded-xl border border-neutral-ivory bg-white cursor-pointer"
            aria-label="Next"
            @click="shift(1)"
          >
            <ChevronRight :size="18" />
          </button>
          <h2 class="ml-2 font-display text-2xl font-bold">{{ titleLabel }}</h2>
          <button
            type="button"
            class="ml-2 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider border border-neutral-ivory bg-white cursor-pointer"
            @click="goToday"
          >
            Today
          </button>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="option in [
              { value: 'month', label: 'Month' },
              { value: 'week', label: 'Week' },
            ]"
            :key="option.value"
            type="button"
            class="px-4 py-2 rounded-xl text-[10px] font-bold uppercase tracking-wider border cursor-pointer"
            :class="view === option.value
              ? 'bg-primary text-white border-primary'
              : 'bg-white border-neutral-ivory text-neutral-black/55'"
            @click="view = option.value as typeof view"
          >
            {{ option.label }}
          </button>

          <select
            v-model="selectedCategory"
            class="px-3 py-2 rounded-xl border border-neutral-ivory bg-white text-xs font-semibold"
            aria-label="Filter by category"
          >
            <option value="all">All categories</option>
            <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">
              {{ cat.name }}
            </option>
          </select>

          <select
            v-model="rangeFilter"
            class="px-3 py-2 rounded-xl border border-neutral-ivory bg-white text-xs font-semibold"
            aria-label="Upcoming or past"
          >
            <option value="all">Upcoming & past</option>
            <option value="upcoming">Upcoming</option>
            <option value="past">Past</option>
          </select>
        </div>
      </div>

      <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        {{ error }}
      </div>

      <div
        v-if="view === 'month'"
        class="rounded-[1.5rem] border border-neutral-ivory bg-white overflow-hidden"
        :aria-busy="loading"
      >
        <div class="grid grid-cols-7 border-b border-neutral-ivory bg-neutral-background/60">
          <div
            v-for="day in weekdays"
            :key="day"
            class="px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/45"
          >
            {{ day }}
          </div>
        </div>

        <div class="grid grid-cols-7 auto-rows-[minmax(7rem,auto)]">
          <div
            v-for="cell in monthCells"
            :key="cell.key"
            class="border-t border-r border-neutral-ivory/80 p-2 min-h-[7rem]"
            :class="[
              cell.inMonth ? 'bg-white' : 'bg-neutral-background/40 text-neutral-black/35',
              isToday(cell.date) ? 'ring-2 ring-inset ring-primary/30' : '',
            ]"
          >
            <div class="text-xs font-bold mb-1.5" :class="isToday(cell.date) ? 'text-primary' : ''">
              {{ cell.date.getDate() }}
            </div>
            <div class="space-y-1">
              <button
                v-for="event in eventsOn(cell.date).slice(0, 3)"
                :key="event.uuid"
                type="button"
                class="w-full text-left truncate px-1.5 py-1 rounded-md text-[10px] font-semibold cursor-pointer hover:brightness-95 border"
                :style="eventColorStyle(event)"
                :title="event.name"
                @click="openEvent(event.slug)"
              >
                {{ event.name }}
              </button>
              <div
                v-if="eventsOn(cell.date).length > 3"
                class="text-[10px] text-neutral-black/40 px-1"
              >
                +{{ eventsOn(cell.date).length - 3 }} more
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-else
        class="grid gap-3 md:grid-cols-7"
        :aria-busy="loading"
      >
        <div
          v-for="day in weekDays"
          :key="dateKey(day)"
          class="rounded-2xl border border-neutral-ivory bg-white p-3 min-h-[12rem]"
          :class="isToday(day) ? 'ring-2 ring-primary/25' : ''"
        >
          <div class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/40">
            {{ day.toLocaleDateString(undefined, { weekday: 'short' }) }}
          </div>
          <div class="text-lg font-display font-bold mb-3" :class="isToday(day) ? 'text-primary' : ''">
            {{ day.getDate() }}
          </div>
          <div class="space-y-2">
            <button
              v-for="event in eventsOn(day)"
              :key="event.uuid"
              type="button"
              class="w-full text-left rounded-xl border px-2.5 py-2 cursor-pointer hover:brightness-95"
              :style="eventColorStyle(event)"
              @click="openEvent(event.slug)"
            >
              <div class="text-xs font-bold leading-snug">{{ event.name }}</div>
              <div v-if="event.category" class="mt-1 text-[10px] uppercase tracking-wider opacity-80">
                {{ event.category.name }}
              </div>
            </button>
            <p v-if="eventsOn(day).length === 0" class="text-[11px] text-neutral-black/35">
              No events
            </p>
          </div>
        </div>
      </div>

      <ul v-if="categories.length" class="flex flex-wrap items-center gap-3 pt-1" aria-label="Category colors">
        <li v-for="cat in categories" :key="cat.slug" class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-neutral-black/60">
          <span
            class="h-2.5 w-2.5 rounded-full border"
            :style="categorySolidStyle(cat.color)"
            aria-hidden="true"
          />
          {{ cat.name }}
        </li>
      </ul>
    </section>
  </div>
</template>
