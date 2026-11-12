<script setup lang="ts">
import { computed } from 'vue';
import type { MemberWorkload } from '../../types/pm';

interface Props {
  teamWorkload: MemberWorkload[];
  startDate: string; // YYYY-MM-DD
  endDate: string;   // YYYY-MM-DD
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'edit-capacity', member: MemberWorkload): void;
}>();

interface CalendarDate {
  dateStr: string;
  dayNumber: number;
  weekdayLabel: string;
  isWeekend: boolean;
  isToday: boolean;
}

const dates = computed<CalendarDate[]>(() => {
  const result: CalendarDate[] = [];
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
    });

    curr.setDate(curr.getDate() + 1);
  }

  return result;
});

function getCellColorClass(status?: string): string {
  switch (status) {
    case 'over_allocated':
      return 'bg-[#be123c]/15 border-[#be123c]/30 text-[#991b1b] font-bold';
    case 'optimal':
      return 'bg-[#105232]/15 border-[#105232]/30 text-[#105232] font-semibold';
    case 'under_utilized':
      return 'bg-[#d97706]/15 border-[#d97706]/30 text-[#92400e] font-medium';
    case 'available':
    default:
      return 'bg-white border-[#ebe8de] text-[#9ca3af]';
  }
}
</script>

<template>
  <div class="border border-[#ebe8de] rounded-2xl bg-white overflow-hidden shadow-xs">
    <!-- Header Legend -->
    <div class="px-4 py-3 bg-[#fbfbf9] border-b border-[#ebe8de] flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="font-bold text-[#640c0e] flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-[#640c0e]" />
        <span>Team Capacity & Daily Workload Heatmap</span>
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-3 text-[11px]">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-xs bg-[#be123c]/20 border border-[#be123c]/40" />
          <span class="text-[#1e1e1e] font-medium">Over-allocated (&gt;100%)</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-xs bg-[#105232]/20 border border-[#105232]/40" />
          <span class="text-[#1e1e1e] font-medium">Optimal (70-100%)</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-xs bg-[#d97706]/20 border border-[#d97706]/40" />
          <span class="text-[#1e1e1e] font-medium">Under-utilized (&lt;70%)</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-xs bg-white border border-[#ebe8de]" />
          <span class="text-[#6b7280]">Available (0%)</span>
        </div>
      </div>
    </div>

    <!-- Heatmap Grid -->
    <div class="overflow-x-auto">
      <table class="w-full text-xs border-collapse">
        <!-- Date Header -->
        <thead>
          <tr class="bg-[#fbfbf9] border-b border-[#ebe8de] text-[#6b7280]">
            <th class="py-2.5 px-3 text-left font-bold text-[#640c0e] w-56 sticky left-0 bg-[#fbfbf9] z-10 border-r border-[#ebe8de]">
              Contributor
            </th>
            <th class="py-2.5 px-2 text-center font-semibold text-[#1e1e1e] w-20 border-r border-[#ebe8de]">
              Capacity
            </th>
            <th
              v-for="d in dates"
              :key="d.dateStr"
              class="py-1 px-1 text-center font-normal min-w-[38px] border-r border-[#ebe8de]/70"
              :class="[
                d.isWeekend ? 'bg-[#faf8f5] text-[#9ca3af]' : 'text-[#1e1e1e]',
                d.isToday ? 'bg-[#640c0e]/10 text-[#640c0e] font-bold border-b-2 border-[#640c0e]' : '',
              ]"
            >
              <div class="text-[9px] uppercase leading-none">{{ d.weekdayLabel }}</div>
              <div class="text-[11px] font-semibold leading-tight mt-0.5">{{ d.dayNumber }}</div>
            </th>
          </tr>
        </thead>

        <!-- Contributor Rows -->
        <tbody class="divide-y divide-[#ebe8de]/50">
          <tr
            v-for="member in teamWorkload"
            :key="member.user.id"
            class="hover:bg-[#fffbf4]/60 transition-colors"
          >
            <!-- Member Identity & Trigger for Capacity Edit -->
            <td class="py-2.5 px-3 sticky left-0 bg-[#fbfbf9] z-10 border-r border-[#ebe8de]">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                  <div class="w-6 h-6 rounded-full bg-white border border-[#ebe8de] flex items-center justify-center text-[10px] font-bold text-[#640c0e] shrink-0">
                    {{ member.user.name.charAt(0) }}
                  </div>
                  <div class="min-w-0">
                    <p class="truncate font-semibold text-[#1e1e1e] text-xs">{{ member.user.name }}</p>
                    <p class="truncate text-[10px] text-[#6b7280]">{{ member.user.email }}</p>
                  </div>
                </div>

                <button
                  type="button"
                  class="text-[10px] text-[#6b7280] hover:text-[#640c0e] ml-2 px-1.5 py-0.5 rounded-lg hover:bg-[#640c0e]/10 transition-colors"
                  title="Configure capacity"
                  @click="emit('edit-capacity', member)"
                >
                  ⚙️
                </button>
              </div>
            </td>

            <!-- Daily Capacity Summary -->
            <td class="py-2.5 px-2 text-center text-[11px] text-[#6b7280] font-mono border-r border-[#ebe8de]">
              {{ member.capacity.daily_hours }}h/d
            </td>

            <!-- Heatmap Cells -->
            <td
              v-for="d in dates"
              :key="d.dateStr"
              class="p-0.5 text-center border-r border-[#ebe8de]/60"
              :class="d.isWeekend ? 'bg-[#faf8f5]/60' : 'bg-white'"
            >
              <div
                class="w-full h-8 rounded-lg border flex flex-col items-center justify-center text-[10px] transition-all"
                :class="getCellColorClass(member.daily_breakdown[d.dateStr]?.status)"
                :title="`${d.dateStr}: ${member.daily_breakdown[d.dateStr]?.allocated_hours || 0}h / ${member.daily_breakdown[d.dateStr]?.capacity_hours || 0}h (${member.daily_breakdown[d.dateStr]?.utilization_rate || 0}%)`"
              >
                <span v-if="(member.daily_breakdown[d.dateStr]?.allocated_hours || 0) > 0">
                  {{ member.daily_breakdown[d.dateStr]?.allocated_hours }}h
                </span>
                <span v-else class="text-[#d1d5db] text-[9px]">—</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

