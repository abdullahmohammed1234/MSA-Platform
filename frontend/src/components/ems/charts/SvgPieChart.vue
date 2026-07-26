<script setup lang="ts">
import { computed, ref } from 'vue';

interface PieSegment {
  label: string;
  value: number;
}

const props = withDefaults(
  defineProps<{
    data: PieSegment[];
    colors?: string[];
  }>(),
  {
    colors: () => [
      '#640c0e', // Burgundy
      '#b02e32', // Academic Red
      '#d97706', // Amber Gold
      '#065f46', // Success Emerald
      '#5a5d61', // Gray
    ],
  }
);

const hoveredIndex = ref<number | null>(null);

const total = computed(() => props.data.reduce((acc, d) => acc + d.value, 0));

const segments = computed(() => {
  if (total.value === 0) return [];
  let accumulatedPercent = 0;
  const radius = 50;
  const circumference = 2 * Math.PI * radius; // ~314.16

  return props.data.map((d, index) => {
    const percent = d.value / total.value;
    const strokeDash = percent * circumference;
    const strokeOffset = circumference - (accumulatedPercent * circumference);
    accumulatedPercent += percent;

    return {
      label: d.label,
      value: d.value,
      percent: round(percent * 100, 1),
      strokeDash: `${strokeDash} ${circumference}`,
      strokeOffset,
      color: props.colors[index % props.colors.length],
    };
  });
});

const round = (num: number, decimals: number) => {
  const t = Math.pow(10, decimals);
  return Math.round(num * t) / t;
};
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
    <div v-if="total === 0" class="flex h-40 items-center justify-center text-xs text-neutral-muted w-full">
      No data available
    </div>
    <template v-else>
      <!-- Donut Circle -->
      <div class="relative w-44 h-44 shrink-0">
        <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
          <circle cx="60" cy="60" r="50" fill="transparent" stroke="#ebe8de" stroke-width="12" />
          <circle
            v-for="(seg, idx) in segments"
            :key="idx"
            cx="60"
            cy="60"
            r="50"
            fill="transparent"
            :stroke="seg.color"
            stroke-width="12"
            :stroke-dasharray="seg.strokeDash"
            :stroke-dashoffset="seg.strokeOffset"
            stroke-linecap="round"
            class="transition-all duration-300 origin-center cursor-pointer"
            :class="hoveredIndex === idx ? 'scale-105 stroke-[14px]' : 'hover:scale-[1.02] hover:stroke-[13px]'"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
          />
        </svg>

        <!-- Center Counter Text -->
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span class="text-xs font-semibold uppercase tracking-wider text-neutral-muted">Total</span>
          <span class="text-xl font-bold text-neutral-black">{{ total }}</span>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex-1 space-y-2.5 min-w-[150px]">
        <div
          v-for="(seg, idx) in segments"
          :key="idx"
          class="flex items-center justify-between text-xs transition-colors rounded-lg p-1.5 cursor-pointer"
          :class="hoveredIndex === idx ? 'bg-neutral-background font-bold text-neutral-black' : 'text-neutral-muted'"
          @mouseenter="hoveredIndex = idx"
          @mouseleave="hoveredIndex = null"
        >
          <div class="flex items-center gap-2">
            <span
              class="w-3 h-3 rounded-full shrink-0"
              :style="{ backgroundColor: seg.color }"
            />
            <span>{{ seg.label }}</span>
          </div>
          <div class="text-right">
            <span class="font-semibold text-neutral-black mr-2">{{ seg.value }}</span>
            <span class="text-[10px] text-neutral-muted/80">({{ seg.percent }}%)</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
