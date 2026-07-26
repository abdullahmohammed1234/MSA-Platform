<script setup lang="ts">
import { computed, ref } from 'vue';

interface BarData {
  label: string;
  value: number;
  secondaryValue?: number;
}

const props = withDefaults(
  defineProps<{
    data: BarData[];
    height?: number;
    barColor?: string;
    secondaryBarColor?: string;
  }>(),
  {
    height: 250,
    barColor: '#640c0e', // Deep Burgundy
    secondaryBarColor: '#d97706', // Amber Gold
  }
);

const hoveredIndex = ref<number | null>(null);

const maxVal = computed(() => {
  let max = 0;
  props.data.forEach((d) => {
    max = Math.max(max, d.value, d.secondaryValue ?? 0);
  });
  return max === 0 ? 10 : Math.ceil(max * 1.1);
});

const bars = computed(() => {
  if (props.data.length === 0) return [];
  const width = 500;
  const paddingX = 40;
  const paddingY = 30;
  const activeWidth = width - paddingX * 2;
  const activeHeight = props.height - paddingY * 2;

  const barGroupWidth = activeWidth / props.data.length;
  const spacing = barGroupWidth * 0.2; // 20% gap
  const singleBarWidth = (barGroupWidth - spacing) / (hasSecondary.value ? 2 : 1);

  return props.data.map((d, index) => {
    const groupX = paddingX + index * barGroupWidth + spacing / 2;

    const primaryH = (d.value / maxVal.value) * activeHeight;
    const primaryY = props.height - paddingY - primaryH;

    let secondaryH = 0;
    let secondaryY = 0;
    if (d.secondaryValue !== undefined) {
      secondaryH = (d.secondaryValue / maxVal.value) * activeHeight;
      secondaryY = props.height - paddingY - secondaryH;
    }

    return {
      label: d.label,
      primary: {
        x: groupX,
        y: primaryY,
        width: singleBarWidth,
        height: Math.max(2, primaryH),
        value: d.value,
      },
      secondary: d.secondaryValue !== undefined ? {
        x: groupX + singleBarWidth,
        y: secondaryY,
        width: singleBarWidth,
        height: Math.max(2, secondaryH),
        value: d.secondaryValue,
      } : null,
    };
  });
});

const hasSecondary = computed(() => props.data.some((d) => d.secondaryValue !== undefined));

const gridLines = computed(() => {
  const steps = 4;
  const paddingY = 30;
  const activeHeight = props.height - paddingY * 2;
  const lines = [];

  for (let i = 0; i <= steps; i++) {
    const value = Math.round((i / steps) * maxVal.value);
    const y = props.height - paddingY - (value / maxVal.value) * activeHeight;
    lines.push({ y, value });
  }
  return lines;
});
</script>

<template>
  <div class="relative w-full">
    <div v-if="data.length === 0" class="flex h-48 items-center justify-center text-xs text-neutral-muted">
      No data available for the selected metrics
    </div>
    <div v-else class="w-full overflow-visible">
      <svg
        viewBox="0 0 500 250"
        preserveAspectRatio="xMidYMid meet"
        class="w-full overflow-visible"
        :style="{ height: `${height}px` }"
      >
        <!-- Horizontal Grid Lines -->
        <g stroke="#ebe8de" stroke-width="1" stroke-dasharray="3,3">
          <line
            v-for="(line, idx) in gridLines"
            :key="idx"
            x1="30"
            :y1="line.y"
            x2="480"
            :y2="line.y"
          />
        </g>

        <!-- Grid Labels (Y-axis) -->
        <g fill="#5a5d61" font-size="9" text-anchor="end">
          <text
            v-for="(line, idx) in gridLines"
            :key="idx"
            x="24"
            :y="line.y + 3"
          >
            {{ line.value }}
          </text>
        </g>

        <!-- Bars -->
        <g>
          <g
            v-for="(bar, idx) in bars"
            :key="idx"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
          >
            <!-- Primary Bar -->
            <rect
              :x="bar.primary.x"
              :y="bar.primary.y"
              :width="bar.primary.width"
              :height="bar.primary.height"
              :fill="barColor"
              rx="2"
              class="transition-all duration-200 hover:brightness-105 cursor-pointer"
            />
            <!-- Secondary Bar (if present) -->
            <rect
              v-if="bar.secondary"
              :x="bar.secondary.x"
              :y="bar.secondary.y"
              :width="bar.secondary.width"
              :height="bar.secondary.height"
              :fill="secondaryBarColor"
              rx="2"
              class="transition-all duration-200 hover:brightness-105 cursor-pointer"
            />
          </g>
        </g>

        <!-- X-axis Labels -->
        <g fill="#5a5d61" font-size="9" text-anchor="middle">
          <text
            v-for="(bar, idx) in bars"
            :key="idx"
            :x="bar.primary.x + (bar.secondary ? bar.primary.width : bar.primary.width / 2)"
            :y="height - 8"
            class="truncate"
            style="max-width: 50px;"
          >
            {{ bar.label.length > 10 ? bar.label.slice(0, 8) + '..' : bar.label }}
          </text>
        </g>
      </svg>

      <!-- Tooltip -->
      <transition name="fade">
        <div
          v-if="hoveredIndex !== null"
          class="absolute z-10 pointer-events-none rounded-lg bg-neutral-black text-white px-2.5 py-1.5 text-xs shadow-md border border-neutral-muted/20"
          :style="{
            left: `${((bars[hoveredIndex].primary.x + (bars[hoveredIndex].secondary ? bars[hoveredIndex].primary.width : bars[hoveredIndex].primary.width / 2)) / 500) * 100}%`,
            top: `${(bars[hoveredIndex].primary.y / height) * 100 - 15}%`,
            transform: 'translate(-50%, -100%)',
          }"
        >
          <p class="font-semibold">{{ bars[hoveredIndex].label }}</p>
          <p class="text-[10px] text-neutral-ivory/80">Primary: {{ bars[hoveredIndex].primary.value }}</p>
          <p v-if="bars[hoveredIndex].secondary" class="text-[10px] text-amber-300">Secondary: {{ bars[hoveredIndex].secondary?.value }}</p>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
