<script setup lang="ts">
import { computed } from 'vue';

export type GanttZoomLevel = 'day' | 'week' | 'month' | 'quarter';

interface Props {
  startDate: string; // YYYY-MM-DD
  endDate: string;   // YYYY-MM-DD
  zoomLevel: GanttZoomLevel;
  dayPitch: number;  // px per day
}

const props = defineProps<Props>();

interface TimelineDay {
  dateStr: string;
  dayNumber: number;
  weekdayLabel: string;
  isWeekend: boolean;
  isToday: boolean;
  width: number;
}

interface TimelineMonth {
  label: string;
  width: number;
}

interface TimelineWeek {
  label: string;
  width: number;
}

interface TimelineQuarter {
  label: string;
  width: number;
}

const days = computed<TimelineDay[]>(() => {
  const result: TimelineDay[] = [];
  const start = new Date(props.startDate + 'T00:00:00');
  const end = new Date(props.endDate + 'T00:00:00');
  const todayStr = new Date().toISOString().split('T')[0];

  const weekdayLetters = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

  const curr = new Date(start);
  while (curr <= end) {
    const dateStr = curr.toISOString().split('T')[0];
    const dayOfWeek = curr.getDay();

    result.push({
      dateStr,
      dayNumber: curr.getDate(),
      weekdayLabel: weekdayLetters[dayOfWeek],
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isToday: dateStr === todayStr,
      width: props.dayPitch,
    });

    curr.setDate(curr.getDate() + 1);
  }

  return result;
});

const months = computed<TimelineMonth[]>(() => {
  const result: TimelineMonth[] = [];
  if (days.value.length === 0) return result;

  let currentMonth = '';
  let currentWidth = 0;

  days.value.forEach((d) => {
    const dObj = new Date(d.dateStr + 'T00:00:00');
    const monthLabel = dObj.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });

    if (monthLabel !== currentMonth) {
      if (currentMonth) {
        result.push({ label: currentMonth, width: currentWidth });
      }
      currentMonth = monthLabel;
      currentWidth = d.width;
    } else {
      currentWidth += d.width;
    }
  });

  if (currentMonth) {
    result.push({ label: currentMonth, width: currentWidth });
  }

  return result;
});

const weeks = computed<TimelineWeek[]>(() => {
  const result: TimelineWeek[] = [];
  if (days.value.length === 0) return result;

  let weekWidth = 0;
  let weekStartDate = days.value[0].dateStr;

  days.value.forEach((d, idx) => {
    const dObj = new Date(d.dateStr + 'T00:00:00');
    const isEndOfWeek = dObj.getDay() === 0 || idx === days.value.length - 1;

    weekWidth += d.width;

    if (isEndOfWeek) {
      const startObj = new Date(weekStartDate + 'T00:00:00');
      const startStr = startObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      const endStr = dObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      result.push({
        label: `${startStr} - ${endStr}`,
        width: weekWidth,
      });

      if (idx < days.value.length - 1) {
        weekStartDate = days.value[idx + 1].dateStr;
        weekWidth = 0;
      }
    }
  });

  return result;
});

const quarters = computed<TimelineQuarter[]>(() => {
  const result: TimelineQuarter[] = [];
  if (days.value.length === 0) return result;

  let currentQuarter = '';
  let currentWidth = 0;

  days.value.forEach((d) => {
    const dObj = new Date(d.dateStr + 'T00:00:00');
    const qNum = Math.floor(dObj.getMonth() / 3) + 1;
    const qLabel = `Q${qNum} ${dObj.getFullYear()}`;

    if (qLabel !== currentQuarter) {
      if (currentQuarter) {
        result.push({ label: currentQuarter, width: currentWidth });
      }
      currentQuarter = qLabel;
      currentWidth = d.width;
    } else {
      currentWidth += d.width;
    }
  });

  if (currentQuarter) {
    result.push({ label: currentQuarter, width: currentWidth });
  }

  return result;
});
</script>

<template>
  <div class="sticky top-0 z-20 bg-[#fbfbf9] border-b border-[#ebe8de] select-none">
    <!-- Top tier: Months / Quarters -->
    <div class="flex border-b border-[#ebe8de] text-xs font-bold text-[#640c0e]">
      <template v-if="zoomLevel === 'quarter'">
        <div
          v-for="(q, idx) in quarters"
          :key="idx"
          class="px-2 py-1.5 text-center truncate border-r border-[#ebe8de] bg-[#fbfbf9]"
          :style="{ width: `${q.width}px` }"
        >
          {{ q.label }}
        </div>
      </template>
      <template v-else>
        <div
          v-for="(m, idx) in months"
          :key="idx"
          class="px-2 py-1.5 text-center truncate border-r border-[#ebe8de] bg-[#fbfbf9]"
          :style="{ width: `${m.width}px` }"
        >
          {{ m.label }}
        </div>
      </template>
    </div>

    <!-- Secondary tier: Days / Weeks -->
    <div class="flex text-[10px] text-[#6b7280]">
      <!-- Day Zoom -->
      <template v-if="zoomLevel === 'day'">
        <div
          v-for="d in days"
          :key="d.dateStr"
          class="py-1 text-center border-r border-[#ebe8de] flex flex-col justify-center"
          :class="[
            d.isWeekend ? 'bg-[#faf8f5] text-[#9ca3af]' : 'bg-white text-[#1e1e1e]',
            d.isToday ? 'bg-[#640c0e]/10 text-[#640c0e] font-bold border-b-2 border-[#640c0e]' : '',
          ]"
          :style="{ width: `${d.width}px` }"
          :title="d.dateStr"
        >
          <span class="leading-none text-[9px]">{{ d.weekdayLabel }}</span>
          <span class="leading-none mt-0.5 text-[11px] font-semibold">{{ d.dayNumber }}</span>
        </div>
      </template>

      <!-- Week Zoom -->
      <template v-else-if="zoomLevel === 'week'">
        <div
          v-for="(w, idx) in weeks"
          :key="idx"
          class="py-1.5 px-2 text-center truncate border-r border-[#ebe8de] font-semibold text-[#1e1e1e] bg-white"
          :style="{ width: `${w.width}px` }"
        >
          {{ w.label }}
        </div>
      </template>

      <!-- Month Zoom -->
      <template v-else-if="zoomLevel === 'month'">
        <div
          v-for="d in days"
          :key="d.dateStr"
          class="py-1 text-center border-r border-[#ebe8de]/60"
          :class="d.isWeekend ? 'bg-[#faf8f5]/60' : 'bg-white'"
          :style="{ width: `${d.width}px` }"
          :title="d.dateStr"
        >
          <span v-if="d.dayNumber === 1 || d.dayNumber === 15" class="text-[9px] font-semibold text-[#1e1e1e]">
            {{ d.dayNumber }}
          </span>
        </div>
      </template>

      <!-- Quarter Zoom -->
      <template v-else>
        <div
          v-for="(m, idx) in months"
          :key="idx"
          class="py-1 px-1 text-center truncate border-r border-[#ebe8de]/60 text-[10px] text-[#1e1e1e] bg-white font-medium"
          :style="{ width: `${m.width}px` }"
        >
          {{ m.label.split(' ')[0] }}
        </div>
      </template>
    </div>
  </div>
</template>

