<script setup lang="ts">
import type { GanttZoomLevel } from './GanttTimelineHeader.vue';

export type GanttGrouping = 'none' | 'section' | 'assignee' | 'status';

interface Props {
  zoomLevel: GanttZoomLevel;
  grouping: GanttGrouping;
  showDependencies: boolean;
  showMilestones: boolean;
  myTasksOnly: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:zoomLevel', val: GanttZoomLevel): void;
  (e: 'update:grouping', val: GanttGrouping): void;
  (e: 'update:showDependencies', val: boolean): void;
  (e: 'update:showMilestones', val: boolean): void;
  (e: 'update:myTasksOnly', val: boolean): void;
  (e: 'scroll-to-today'): void;
}>();
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 bg-white border-b border-[#ebe8de] text-xs">
    <!-- Left: Zoom & Today Controls -->
    <div class="flex items-center gap-2">
      <span class="text-[#6b7280] font-semibold mr-1">Zoom:</span>
      <div class="inline-flex rounded-xl shadow-2xs bg-[#fbfbf9] p-0.5 border border-[#ebe8de]">
        <button
          type="button"
          class="px-2.5 py-1 text-xs rounded-lg transition-colors"
          :class="zoomLevel === 'day' ? 'bg-[#640c0e] text-white font-bold shadow-2xs' : 'text-[#6b7280] hover:text-[#1e1e1e] font-medium'"
          @click="emit('update:zoomLevel', 'day')"
        >
          Day
        </button>
        <button
          type="button"
          class="px-2.5 py-1 text-xs rounded-lg transition-colors"
          :class="zoomLevel === 'week' ? 'bg-[#640c0e] text-white font-bold shadow-2xs' : 'text-[#6b7280] hover:text-[#1e1e1e] font-medium'"
          @click="emit('update:zoomLevel', 'week')"
        >
          Week
        </button>
        <button
          type="button"
          class="px-2.5 py-1 text-xs rounded-lg transition-colors"
          :class="zoomLevel === 'month' ? 'bg-[#640c0e] text-white font-bold shadow-2xs' : 'text-[#6b7280] hover:text-[#1e1e1e] font-medium'"
          @click="emit('update:zoomLevel', 'month')"
        >
          Month
        </button>
        <button
          type="button"
          class="px-2.5 py-1 text-xs rounded-lg transition-colors"
          :class="zoomLevel === 'quarter' ? 'bg-[#640c0e] text-white font-bold shadow-2xs' : 'text-[#6b7280] hover:text-[#1e1e1e] font-medium'"
          @click="emit('update:zoomLevel', 'quarter')"
        >
          Quarter
        </button>
      </div>

      <button
        type="button"
        class="ml-2 px-2.5 py-1 bg-[#fbfbf9] hover:bg-[#f4f2ea] border border-[#ebe8de] text-[#1e1e1e] rounded-xl text-xs font-semibold transition-colors"
        @click="emit('scroll-to-today')"
      >
        Today
      </button>
    </div>

    <!-- Center: Grouping Selector -->
    <div class="flex items-center gap-2">
      <span class="text-[#6b7280] font-semibold">Group by:</span>
      <select
        :value="grouping"
        class="bg-[#fbfbf9] border border-[#ebe8de] text-[#1e1e1e] rounded-xl px-2.5 py-1 text-xs focus:ring-1 focus:ring-[#640c0e] focus:outline-none font-medium"
        @change="emit('update:grouping', ($event.target as HTMLSelectElement).value as GanttGrouping)"
      >
        <option value="none">Flat (None)</option>
        <option value="section">Section / Task List</option>
        <option value="assignee">Assignee</option>
        <option value="status">Status</option>
      </select>
    </div>

    <!-- Right: Filter Toggles -->
    <div class="flex items-center gap-3 text-[#1e1e1e]">
      <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
        <input
          type="checkbox"
          :checked="showDependencies"
          class="rounded border-[#ebe8de] text-[#640c0e] focus:ring-[#640c0e] w-3.5 h-3.5"
          @change="emit('update:showDependencies', ($event.target as HTMLInputElement).checked)"
        />
        <span>Dependencies</span>
      </label>

      <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
        <input
          type="checkbox"
          :checked="showMilestones"
          class="rounded border-[#ebe8de] text-[#640c0e] focus:ring-[#640c0e] w-3.5 h-3.5"
          @change="emit('update:showMilestones', ($event.target as HTMLInputElement).checked)"
        />
        <span>Milestones</span>
      </label>

      <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
        <input
          type="checkbox"
          :checked="myTasksOnly"
          class="rounded border-[#ebe8de] text-[#640c0e] focus:ring-[#640c0e] w-3.5 h-3.5"
          @change="emit('update:myTasksOnly', ($event.target as HTMLInputElement).checked)"
        />
        <span>My Tasks</span>
      </label>
    </div>
  </div>
</template>

