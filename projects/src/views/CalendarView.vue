<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { projectsApi } from '../api/projects';
import type { CalendarEvent } from '../types/pm';
import TaskDetailModal from '../components/tasks/TaskDetailModal.vue';
import CalendarSubscribeModal from '../components/calendar/CalendarSubscribeModal.vue';
import {
  ChevronLeft,
  ChevronRight,
  Loader2,
  Calendar,
  Rss,
  AlertTriangle,
} from 'lucide-vue-next';

const router = useRouter();
const events = ref<CalendarEvent[]>([]);
const isLoading = ref(true);
const currentDate = ref(new Date());

const filterType = ref<'all' | 'project' | 'milestone' | 'task'>('all');
const myTasksOnly = ref(false);

// Modals
const showSubscribeModal = ref(false);
const activeTaskUuid = ref<string | null>(null);
const activeProjectUuid = ref<string | null>(null);

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const currentMonthLabel = computed(() => {
  return `${monthNames[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`;
});

const formatDateStr = (d: Date): string => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const loadEvents = async () => {
  isLoading.value = true;
  try {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();

    const startObj = new Date(year, month - 1, 1);
    const endObj = new Date(year, month + 2, 0);

    const start = formatDateStr(startObj);
    const end = formatDateStr(endObj);

    events.value = await projectsApi.getCalendarEvents({
      start,
      end,
      type: filterType.value !== 'all' ? filterType.value : undefined,
      my_tasks_only: myTasksOnly.value || undefined,
    });
  } catch (err) {
    console.error('Failed to load calendar events:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadEvents();
});

const prevMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
  loadEvents();
};

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
  loadEvents();
};

const today = () => {
  currentDate.value = new Date();
  loadEvents();
};

// Check if an event falls on or spans across a given calendar date
const isEventOnDate = (event: CalendarEvent, dateStr: string): boolean => {
  const startRaw = event.start || event.start_date || event.due_date;
  const endRaw = event.end || event.due_date || startRaw;
  if (!startRaw || !endRaw) return false;
  let start = String(startRaw).substring(0, 10);
  let end = String(endRaw).substring(0, 10);
  if (start > end) {
    const tmp = start;
    start = end;
    end = tmp;
  }
  return dateStr >= start && dateStr <= end;
};

// Calendar matrix calculation
const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();

  const firstDayOfMonth = new Date(year, month, 1);
  const lastDayOfMonth = new Date(year, month + 1, 0);

  const daysInMonth = lastDayOfMonth.getDate();
  const startingDayOfWeek = firstDayOfMonth.getDay(); // 0 is Sunday

  const days: { dateStr: string; dayNumber: number; isCurrentMonth: boolean; events: CalendarEvent[] }[] = [];

  // Previous month trailing days
  const prevMonthLastDay = new Date(year, month, 0).getDate();
  for (let i = startingDayOfWeek - 1; i >= 0; i--) {
    const d = prevMonthLastDay - i;
    const dateObj = new Date(year, month - 1, d);
    const dateStr = formatDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      events: events.value.filter((e) => isEventOnDate(e, dateStr)),
    });
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    const dateObj = new Date(year, month, d);
    const dateStr = formatDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      events: events.value.filter((e) => isEventOnDate(e, dateStr)),
    });
  }

  // Next month leading days to complete grid
  const remaining = (7 - (days.length % 7)) % 7;
  for (let d = 1; d <= remaining; d++) {
    const dateObj = new Date(year, month + 1, d);
    const dateStr = formatDateStr(dateObj);
    days.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      events: events.value.filter((e) => isEventOnDate(e, dateStr)),
    });
  }

  return days;
});

const handleEventClick = (event: CalendarEvent) => {
  if (event.type === 'task' && event.uuid && event.project?.uuid) {
    activeTaskUuid.value = event.uuid;
    activeProjectUuid.value = event.project.uuid;
  } else if (event.type === 'milestone' && event.project?.uuid) {
    router.push(`/projects/${event.project.uuid}?tab=milestones`);
  } else if (event.project?.uuid) {
    router.push(`/projects/${event.project.uuid}`);
  }
};

const handleTaskUpdated = () => {
  loadEvents();
};

const agendaEvents = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();
  const start = formatDateStr(new Date(year, month, 1));
  const end = formatDateStr(new Date(year, month + 1, 0));

  return [...events.value]
    .filter((ev) => {
      const s = String(ev.start || ev.start_date || ev.due_date || '').substring(0, 10);
      const e = String(ev.end || ev.due_date || s).substring(0, 10);
      if (!s) return false;
      return e >= start && s <= end;
    })
    .sort((a, b) => {
      const as = String(a.start || a.start_date || a.due_date || '');
      const bs = String(b.start || b.start_date || b.due_date || '');
      return as.localeCompare(bs);
    });
});
</script>

<template>
  <div class="flex flex-col gap-[var(--spacing-section)]">
    <header class="pm-page-header">
      <div class="min-w-0">
        <h1 class="pm-page-title flex items-center gap-2">
          <Calendar class="w-6 h-6 sm:w-7 sm:h-7 text-[#640c0e] shrink-0" />
          <span>Internal Calendar</span>
        </h1>
        <p class="pm-page-subtitle">
          Monitor multi-day task ranges, milestone targets, and project schedules across MSA.
        </p>
      </div>

      <!-- Sync / Subscribe & Month Switcher -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="showSubscribeModal = true"
          class="px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs font-bold text-[#640c0e] bg-white hover:bg-[#fbfbf9] flex items-center gap-1.5 transition-colors shadow-2xs"
        >
          <Rss class="w-3.5 h-3.5" />
          <span>Sync & Subscribe</span>
        </button>

        <button
          type="button"
          @click="today"
          class="px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs font-bold text-[#1e1e1e] bg-white hover:bg-[#fbfbf9]"
        >
          Today
        </button>
        <div class="flex items-center border border-[#ebe8de] rounded-xl bg-white overflow-hidden shadow-2xs">
          <button
            type="button"
            @click="prevMonth"
            class="p-2 hover:bg-[#fbfbf9] text-[#6b7280] hover:text-[#1e1e1e]"
            title="Previous Month"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="px-3 text-xs font-bold text-[#1e1e1e] min-w-[130px] text-center font-display">
            {{ currentMonthLabel }}
          </span>
          <button
            type="button"
            @click="nextMonth"
            class="p-2 hover:bg-[#fbfbf9] text-[#6b7280] hover:text-[#1e1e1e]"
            title="Next Month"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </header>

    <!-- Filters toolbar -->
    <div class="pm-panel p-3.5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="filterType = 'all'; loadEvents()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="filterType === 'all' ? 'bg-[#640c0e] text-white' : 'bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]'"
        >
          All Events & Periods
        </button>
        <button
          type="button"
          @click="filterType = 'project'; loadEvents()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="filterType === 'project' ? 'bg-[#640c0e] text-white' : 'bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]'"
        >
          🏁 Projects
        </button>
        <button
          type="button"
          @click="filterType = 'milestone'; loadEvents()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="filterType === 'milestone' ? 'bg-[#640c0e] text-white' : 'bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]'"
        >
          🎯 Milestones
        </button>
        <button
          type="button"
          @click="filterType = 'task'; loadEvents()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
          :class="filterType === 'task' ? 'bg-[#640c0e] text-white' : 'bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]'"
        >
          📝 Tasks
        </button>
      </div>

      <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-xs font-semibold text-[#1e1e1e] cursor-pointer">
          <input
            type="checkbox"
            v-model="myTasksOnly"
            @change="loadEvents"
            class="rounded text-[#640c0e] focus:ring-[#640c0e]"
          />
          <span>My Assigned Tasks Only</span>
        </label>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
    </div>

    <!-- Mobile / small tablet agenda -->
    <div v-else class="md:hidden space-y-2">
      <div
        v-if="agendaEvents.length === 0"
        class="bg-white rounded-2xl border border-[#ebe8de] p-8 text-center text-xs text-[#6b7280]"
      >
        No events in {{ currentMonthLabel }}.
      </div>
      <button
        v-for="ev in agendaEvents"
        :key="`agenda-${ev.id}`"
        type="button"
        class="w-full text-left bg-white rounded-2xl border border-[#ebe8de] p-4 shadow-xs hover:border-[#640c0e]/30 transition-colors"
        @click="handleEventClick(ev)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-bold text-[#1e1e1e] truncate">{{ ev.title }}</p>
            <p class="text-[11px] text-[#6b7280] capitalize">
              {{ ev.type }}
              <span v-if="ev.project?.name"> · {{ ev.project.name }}</span>
            </p>
            <p class="text-[11px] font-semibold text-[#640c0e]">
              {{ String(ev.start || ev.start_date || ev.due_date || '').substring(0, 10) }}
              <span v-if="ev.end && ev.end !== ev.start"> → {{ String(ev.end).substring(0, 10) }}</span>
            </p>
          </div>
          <AlertTriangle
            v-if="ev.is_conflict"
            class="w-4 h-4 text-red-600 shrink-0"
            title="Scheduling conflict detected"
          />
        </div>
      </button>
    </div>

    <!-- Calendar Grid (tablet+) -->
    <div v-if="!isLoading" class="hidden md:block bg-white rounded-2xl border border-[#ebe8de] shadow-xs overflow-hidden">
      <!-- Day Headers -->
      <div class="grid grid-cols-7 border-b border-[#ebe8de] bg-[#fffbf4] text-center text-xs font-bold text-[#6b7280] py-2.5">
        <div>Sun</div>
        <div>Mon</div>
        <div>Tue</div>
        <div>Wed</div>
        <div>Thu</div>
        <div>Fri</div>
        <div>Sat</div>
      </div>

      <!-- Days Grid -->
      <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-[#ebe8de]">
        <div
          v-for="(day, idx) in calendarDays"
          :key="idx"
          class="min-h-[110px] p-2 transition-colors flex flex-col justify-between"
          :class="[
            day.isCurrentMonth ? 'bg-white' : 'bg-[#faf8f5]/60 text-[#a0a0a0]',
          ]"
        >
          <div class="flex items-center justify-between">
            <span
              class="text-xs font-bold"
              :class="day.isCurrentMonth ? 'text-[#1e1e1e]' : 'text-[#a0a0a0]'"
            >
              {{ day.dayNumber }}
            </span>
            <span
              v-if="day.events.length > 0"
              class="text-[10px] font-semibold text-[#6b7280]"
            >
              {{ day.events.length }}
            </span>
          </div>

          <!-- Event Chips -->
          <div class="space-y-1 mt-1 overflow-y-auto max-h-24">
            <!-- Event Chip -->
            <button
              v-for="ev in day.events"
              :key="ev.id + '-' + day.dateStr"
              type="button"
              @click="handleEventClick(ev)"
              class="w-full text-left px-2 py-1 rounded-md text-[10px] font-semibold truncate block transition-all shadow-2xs group relative"
              :class="[
                ev.type === 'project'
                  ? 'border border-[#640c0e]/30 bg-[#640c0e]/10 text-[#640c0e] hover:bg-[#640c0e]/20 font-bold'
                  : 'hover:opacity-90',
              ]"
              :style="ev.type === 'project' ? {} : {
                backgroundColor: ev.is_multi_day && day.dateStr !== ev.start && day.dateStr !== ev.end
                  ? `${ev.color || '#640c0e'}10`
                  : `${ev.color || '#640c0e'}18`,
                color: ev.color || '#640c0e',
                borderLeft: `3px solid ${ev.color || '#640c0e'}`,
              }"
              :title="`${ev.title} (${ev.type}: ${ev.start} → ${ev.end})`"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="truncate flex items-center gap-1 font-semibold">
                  <!-- Badges / Icons -->
                  <AlertTriangle
                    v-if="ev.is_conflict"
                    class="w-3 h-3 text-red-600 shrink-0 inline animate-pulse"
                    title="Scheduling conflict detected"
                  />
                  <span
                    v-if="ev.type === 'milestone' && ev.condition === 'delayed'"
                    class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block shrink-0"
                    title="Delayed milestone"
                  />
                  <span
                    v-else-if="ev.type === 'milestone' && ev.condition === 'at_risk'"
                    class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block shrink-0"
                    title="At risk milestone"
                  />

                  <!-- Display Title / Project Name on each day -->
                  <span class="truncate">{{ ev.title }}</span>
                </span>

                <!-- Multi-day indicator -->
                <span
                  v-if="ev.is_multi_day && day.dateStr === ev.start"
                  class="text-[7.5px] font-mono px-1 py-0.2 rounded bg-black/5 opacity-80 shrink-0 font-bold uppercase"
                  title="Start of deliverable window"
                >
                  Start
                </span>
                <span
                  v-else-if="ev.is_multi_day && day.dateStr === ev.end"
                  class="text-[7.5px] font-mono px-1 py-0.2 rounded bg-black/5 opacity-80 shrink-0 font-bold uppercase"
                  title="Due / End of deliverable window"
                >
                  Due
                </span>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <CalendarSubscribeModal
      :is-open="showSubscribeModal"
      @close="showSubscribeModal = false"
    />

    <TaskDetailModal
      v-if="activeTaskUuid && activeProjectUuid"
      :is-open="!!activeTaskUuid"
      :task-uuid="activeTaskUuid"
      :project-uuid="activeProjectUuid"
      @close="activeTaskUuid = null; activeProjectUuid = null"
      @task-updated="handleTaskUpdated"
      @task-deleted="handleTaskUpdated"
    />
  </div>
</template>
