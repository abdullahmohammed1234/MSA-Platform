<template>
  <div class="relative w-full" ref="container">
    <div v-if="data.length === 0" class="flex items-center justify-center h-48 border border-dashed border-neutral-ivory rounded-xl text-xs text-neutral-muted italic">
      No chart data available.
    </div>
    
    <div v-else class="space-y-2">
      <!-- Chart SVG Canvas -->
      <svg 
        class="w-full h-auto overflow-visible"
        :viewBox="`0 0 ${width} ${height}`"
        :height="height"
      >
        <!-- Gradients -->
        <defs>
          <linearGradient id="area-grad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#640c0e" stop-opacity="0.25"/>
            <stop offset="100%" stop-color="#640c0e" stop-opacity="0.0"/>
          </linearGradient>
        </defs>

        <!-- Grid Lines -->
        <g v-if="type !== 'donut'">
          <line 
            v-for="i in 4" 
            :key="i"
            x1="40" 
            :y1="gridY(i)" 
            :x2="width - 20" 
            :y2="gridY(i)" 
            stroke="#ebe8de" 
            stroke-width="1"
            stroke-dasharray="4 4"
          />
        </g>

        <!-- Area / Line Chart Path -->
        <g v-if="type === 'area' || type === 'line'">
          <!-- Area Under the Line -->
          <path 
            v-if="type === 'area'"
            :d="areaPath" 
            fill="url(#area-grad)"
          />
          <!-- The Line itself -->
          <path 
            :d="linePath" 
            fill="none" 
            stroke="#640c0e" 
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- Data Points -->
          <circle 
            v-for="(pt, idx) in points" 
            :key="idx"
            :cx="pt.x" 
            :cy="pt.y" 
            r="4" 
            fill="#ffffff" 
            stroke="#640c0e" 
            stroke-width="2.5"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
            class="cursor-pointer transition-all duration-200 hover:scale-125"
          />
        </g>

        <!-- Bar Chart -->
        <g v-else-if="type === 'bar'">
          <rect 
            v-for="(bar, idx) in barPoints" 
            :key="idx"
            :x="bar.x" 
            :y="bar.y" 
            :width="bar.w" 
            :height="bar.h" 
            fill="#b02e32" 
            rx="4"
            class="transition-all duration-200 hover:fill-primary cursor-pointer"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
          />
        </g>

        <!-- Donut Chart -->
        <g v-else-if="type === 'donut'" transform="translate(250, 100)">
          <!-- Background Circle -->
          <circle 
            r="70" 
            fill="none" 
            stroke="#ebe8de" 
            stroke-width="16"
          />
          <!-- Donut Segments -->
          <circle 
            v-for="(seg, idx) in donutSegments"
            :key="idx"
            r="70" 
            fill="none" 
            :stroke="seg.color" 
            stroke-width="16"
            :stroke-dasharray="`${seg.dashArray} ${seg.circumference}`"
            :stroke-dashoffset="-seg.dashOffset"
            transform="rotate(-90)"
            class="transition-all duration-300 cursor-pointer"
            @mouseenter="hoveredIndex = idx"
            @mouseleave="hoveredIndex = null"
          />
        </g>

        <!-- X Axis Labels (Bottom) -->
        <g v-if="type !== 'donut'">
          <text 
            v-for="(label, idx) in visibleLabels" 
            :key="idx"
            :x="label.x" 
            :y="height - 5" 
            text-anchor="middle"
            fill="#5a5d61"
            font-size="9"
            font-family="sans-serif"
          >
            {{ label.text }}
          </text>
        </g>
      </svg>

      <!-- Hover Tooltip -->
      <div 
        v-if="hoveredIndex !== null" 
        class="text-[10px] bg-neutral-black text-white py-1 px-3 rounded-md shadow-soft flex justify-between items-center transition-all duration-150"
      >
        <span class="font-semibold uppercase tracking-wider">{{ labels[hoveredIndex] }}:</span>
        <span class="font-mono font-bold ml-2 text-primary-light">{{ data[hoveredIndex] }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

const props = withDefaults(defineProps<{
  type?: 'area' | 'line' | 'bar' | 'donut';
  data: number[];
  labels: string[];
  height?: number;
}>(), {
  type: 'area',
  height: 200
});

const width = ref(500);
const padding = 30;
const hoveredIndex = ref<number | null>(null);

const maxVal = computed(() => {
  const max = Math.max(...props.data, 0);
  return max === 0 ? 10 : max;
});

// Grid lines generator helper
const gridY = (i: number) => {
  const chartHeight = props.height - padding * 2;
  return padding + (chartHeight / 4) * (i - 1);
};

// Line/Area points builder
const points = computed(() => {
  if (props.data.length === 0) return [];
  const chartWidth = width.value - 60;
  const chartHeight = props.height - padding * 2;
  const stepX = props.data.length > 1 ? chartWidth / (props.data.length - 1) : chartWidth;

  return props.data.map((val, idx) => {
    const x = 40 + idx * stepX;
    const y = props.height - padding - (val / maxVal.value) * chartHeight;
    return { x, y };
  });
});

const linePath = computed(() => {
  if (points.value.length === 0) return '';
  return points.value.reduce((path, pt, idx) => {
    return path + (idx === 0 ? `M ${pt.x} ${pt.y}` : ` L ${pt.x} ${pt.y}`);
  }, '');
});

const areaPath = computed(() => {
  if (points.value.length === 0) return '';
  const first = points.value[0];
  const last = points.value[points.value.length - 1];
  const chartBottom = props.height - padding;
  return `${linePath.value} L ${last.x} ${chartBottom} L ${first.x} ${chartBottom} Z`;
});

// Bar chart rects builder
const barPoints = computed(() => {
  if (props.data.length === 0) return [];
  const chartWidth = width.value - 60;
  const chartHeight = props.height - padding * 2;
  const barWidth = (chartWidth / props.data.length) * 0.6;
  const stepX = chartWidth / props.data.length;

  return props.data.map((val, idx) => {
    const w = barWidth;
    const h = (val / maxVal.value) * chartHeight;
    const x = 40 + idx * stepX + (stepX - barWidth) / 2;
    const y = props.height - padding - h;
    return { x, y, w, h };
  });
});

// Donut segments builder
const donutSegments = computed(() => {
  const total = props.data.reduce((sum, v) => sum + v, 0);
  if (total === 0) return [];

  const circumference = 2 * Math.PI * 70;
  let currentOffset = 0;
  
  const colors = ['#640c0e', '#b02e32', '#c94a4e', '#fbbf24', '#065f46', '#0ea5e9'];

  return props.data.map((val, idx) => {
    const proportion = val / total;
    const dashArray = proportion * circumference;
    const dashOffset = currentOffset;
    currentOffset += dashArray;

    return {
      dashArray,
      dashOffset,
      circumference,
      color: colors[idx % colors.length],
    };
  });
});

// Horizontal label filtering
const visibleLabels = computed(() => {
  if (points.value.length === 0) return [];
  const step = Math.ceil(props.data.length / 5);
  return points.value
    .map((pt, idx) => ({
      x: pt.x,
      text: props.labels[idx],
      index: idx,
    }))
    .filter((_, idx) => idx % step === 0 || idx === props.data.length - 1);
});
</script>
