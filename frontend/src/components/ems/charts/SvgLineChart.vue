<script setup lang="ts">
import { computed, ref } from 'vue';

interface DataPoint {
  label: string;
  value: number;
}

const props = withDefaults(
  defineProps<{
    data: DataPoint[];
    height?: number;
    strokeColor?: string;
    fillColor?: string;
  }>(),
  {
    height: 250,
    strokeColor: '#640c0e', // Deep Burgundy
    fillColor: 'rgba(100, 12, 14, 0.1)',
  }
);

const hoveredIndex = ref<number | null>(null);

const values = computed(() => props.data.map((d) => d.value));

const maxVal = computed(() => {
  const m = Math.max(...values.value, 0);
  return m === 0 ? 10 : Math.ceil(m * 1.15); // Add 15% headroom
});

const points = computed(() => {
  if (props.data.length === 0) return [];
  const width = 500;
  const paddingX = 40;
  const paddingY = 30;
  const activeWidth = width - paddingX * 2;
  const activeHeight = props.height - paddingY * 2;

  return props.data.map((d, index) => {
    const x = paddingX + (index / Math.max(1, props.data.length - 1)) * activeWidth;
    const y = props.height - paddingY - (d.value / maxVal.value) * activeHeight;
    return { x, y, label: d.label, value: d.value };
  });
});

const pathD = computed(() => {
  if (points.value.length === 0) return '';
  return points.value.reduce((acc, p, index) => {
    return acc + `${index === 0 ? 'M' : 'L'} ${p.x} ${p.y}`;
  }, '');
});

const areaD = computed(() => {
  if (points.value.length === 0) return '';
  const paddingX = points.value[0].x;
  const lastX = points.value[points.value.length - 1].x;
  const bottomY = props.height - 30;
  return `${pathD.value} L ${lastX} ${bottomY} L ${paddingX} ${bottomY} Z`;
});

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
      No data available for the selected period
    </div>
    <div v-else class="w-full overflow-visible">
      <svg
        viewBox="0 0 500 250"
        preserveAspectRatio="xMidYMid meet"
        class="w-full overflow-visible"
        :style="{ height: `${height}px` }"
      >
        <defs>
          <!-- Gradient fill -->
          <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#640c0e" stop-opacity="0.25" />
            <stop offset="100%" stop-color="#640c0e" stop-opacity="0.0" />
          </linearGradient>
        </defs>

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

        <!-- Gradient Area -->
        <path :d="areaD" fill="url(#chartGradient)" />

        <!-- Line Path -->
        <path
          :d="pathD"
          fill="none"
          stroke="#640c0e"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />

        <!-- interactive Hover Hotspots and Points -->
        <g>
          <g
            v-for="(point, idx) in points"
            :key="idx"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
          >
            <!-- Highlighted outer circle -->
            <circle
              v-if="hoveredIndex === idx"
              :cx="point.x"
              :cy="point.y"
              r="6.5"
              fill="#640c0e"
              fill-opacity="0.3"
            />
            <!-- Solid inner point -->
            <circle
              :cx="point.x"
              :cy="point.y"
              r="4"
              fill="#ffffff"
              stroke="#640c0e"
              stroke-width="2"
              class="transition-all duration-150"
            />
            <!-- Transparent large capture circle for touch/mouse -->
            <circle
              :cx="point.x"
              :cy="point.y"
              r="15"
              fill="transparent"
              class="cursor-pointer"
            />
          </g>
        </g>

        <!-- X-axis labels (cap to start and end for readability) -->
        <g fill="#5a5d61" font-size="9" text-anchor="middle">
          <text :x="points[0].x" :y="height - 8" text-anchor="start">
            {{ points[0].label }}
          </text>
          <text
            v-if="points.length > 2"
            :x="points[Math.floor(points.length / 2)].x"
            :y="height - 8"
          >
            {{ points[Math.floor(points.length / 2)].label }}
          </text>
          <text :x="points[points.length - 1].x" :y="height - 8" text-anchor="end">
            {{ points[points.length - 1].label }}
          </text>
        </g>
      </svg>

      <!-- Tooltip -->
      <transition name="fade">
        <div
          v-if="hoveredIndex !== null"
          class="absolute z-10 pointer-events-none rounded-lg bg-neutral-black text-white px-2.5 py-1.5 text-xs shadow-md border border-neutral-muted/20"
          :style="{
            left: `${(points[hoveredIndex].x / 500) * 100}%`,
            top: `${(points[hoveredIndex].y / height) * 100 - 15}%`,
            transform: 'translate(-50%, -100%)',
          }"
        >
          <p class="font-semibold">{{ points[hoveredIndex].label }}</p>
          <p class="text-[10px] text-neutral-ivory/80">Value: {{ points[hoveredIndex].value }}</p>
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
