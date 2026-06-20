<script setup lang="ts">
import { ref, computed } from 'vue';
import type { ChartProps } from './types';

interface ExtendedProps extends ChartProps {
  type?: 'line' | 'bar' | 'pie' | 'area';
}

const props = withDefaults(defineProps<ExtendedProps>(), {
  type: 'line',
  height: 260,
  title: '',
  color: '#640c0e' // Maroon Brand primary
});

const activeIndex = ref<number | null>(null);

const maxVal = computed(() => {
  if (props.data.length === 0) return 1;
  const vals = props.data.map(d => d.value);
  return Math.max(...vals, 1);
});

const totalVal = computed(() => {
  return props.data.reduce((sum, d) => sum + d.value, 0);
});

// Dimensions
const svgWidth = 500;
const svgHeight = 250;
const paddingLeft = 50;
const paddingRight = 20;
const paddingTop = 20;
const paddingBottom = 40;

const plotWidth = svgWidth - paddingLeft - paddingRight;
const plotHeight = svgHeight - paddingTop - paddingBottom;

// Coordinate calculations for line, area, and bar charts
const points = computed(() => {
  if (props.data.length === 0) return [];
  const N = props.data.length;
  return props.data.map((d, i) => {
    // Spacing along X
    const x = paddingLeft + (i / Math.max(N - 1, 1)) * plotWidth;
    // Scale along Y
    const ratio = d.value / maxVal.value;
    const y = paddingTop + (1 - ratio) * plotHeight;
    return { x, y, label: d.label, value: d.value };
  });
});

// Bar coordinate calculations
const bars = computed(() => {
  if (props.data.length === 0) return [];
  const N = props.data.length;
  const barWidth = (plotWidth / N) * 0.6;
  const colSpacing = plotWidth / N;
  
  return props.data.map((d, i) => {
    const x = paddingLeft + (i * colSpacing) + (colSpacing - barWidth) / 2;
    const ratio = d.value / maxVal.value;
    const barHeight = ratio * plotHeight;
    const y = paddingTop + plotHeight - barHeight;
    return { x, y, width: barWidth, height: barHeight, label: d.label, value: d.value };
  });
});

// Path definitions
const linePath = computed(() => {
  if (points.value.length === 0) return '';
  return points.value.reduce((path, pt, i) => {
    return path + (i === 0 ? `M ${pt.x} ${pt.y}` : ` L ${pt.x} ${pt.y}`);
  }, '');
});

const areaPath = computed(() => {
  if (points.value.length === 0) return '';
  const first = points.value[0];
  const last = points.value[points.value.length - 1];
  const base = linePath.value;
  return `${base} L ${last.x} ${paddingTop + plotHeight} L ${first.x} ${paddingTop + plotHeight} Z`;
});

// Pie Chart Calculations
const pieSlices = computed(() => {
  let accumulatedAngle = 0;
  const cx = 250;
  const cy = 110;
  const r = 85;
  
  return props.data.map((d) => {
    const percentage = totalVal.value > 0 ? d.value / totalVal.value : 0;
    const angle = percentage * 360;
    
    // Convert polar coordinates to Cartesian for SVG drawing
    const getCoordinates = (angleDeg: number) => {
      const angleRad = ((angleDeg - 90) * Math.PI) / 180;
      return {
        x: cx + r * Math.cos(angleRad),
        y: cy + r * Math.sin(angleRad)
      };
    };

    const start = getCoordinates(accumulatedAngle);
    const end = getCoordinates(accumulatedAngle + angle);
    const largeArcFlag = angle > 180 ? 1 : 0;
    
    // Build path
    const pathData = [
      `M ${cx} ${cy}`,
      `L ${start.x} ${start.y}`,
      `A ${r} ${r} 0 ${largeArcFlag} 1 ${end.x} ${end.y}`,
      'Z'
    ].join(' ');

    const midAngle = accumulatedAngle + angle / 2;
    const labelPos = {
      x: cx + (r * 0.6) * Math.cos(((midAngle - 90) * Math.PI) / 180),
      y: cy + (r * 0.6) * Math.sin(((midAngle - 90) * Math.PI) / 180)
    };

    accumulatedAngle += angle;
    return {
      path: pathData,
      label: d.label,
      value: d.value,
      percentage: (percentage * 100).toFixed(0),
      labelX: labelPos.x,
      labelY: labelPos.y
    };
  });
});
</script>

<template>
  <div class="w-full bg-white border border-neutral-ivory rounded-2xl p-5 shadow-soft flex flex-col">
    <!-- Chart Header -->
    <div class="flex items-center justify-between border-b border-neutral-ivory/50 pb-3 mb-4 flex-shrink-0">
      <h3 class="text-sm font-bold text-neutral-black tracking-wide uppercase font-display">
        {{ title }}
      </h3>
      
      <!-- Interactive Tooltip Display -->
      <div v-if="activeIndex !== null" class="text-xs">
        <span class="text-neutral-muted font-medium mr-1.5">{{ data[activeIndex].label }}:</span>
        <span class="text-primary font-bold">{{ data[activeIndex].value }}</span>
      </div>
      <div v-else class="text-[10px] text-neutral-muted uppercase tracking-[0.1em] font-semibold">
        Hover elements for detail
      </div>
    </div>

    <!-- SVG Chart Canvas -->
    <div class="relative w-full overflow-hidden flex items-center justify-center flex-1">
      <svg
        viewBox="0 0 500 250"
        class="w-full h-auto select-none"
        :style="{ height: `${height}px` }"
      >
        <!-- Gradients definition -->
        <defs>
          <linearGradient id="area-grad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="color" stop-opacity="0.28" />
            <stop offset="100%" :stop-color="color" stop-opacity="0.0" />
          </linearGradient>
          <linearGradient id="bar-grad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="color" stop-opacity="1.0" />
            <stop offset="100%" :stop-color="color" stop-opacity="0.8" />
          </linearGradient>
        </defs>

        <!-- GRIDLINES & AXES (For Line, Area, Bar) -->
        <g v-if="type !== 'pie'" class="text-neutral-ivory">
          <!-- Horizontal Y gridlines -->
          <line
            v-for="i in 4"
            :key="`grid-y-${i}`"
            :x1="paddingLeft"
            :y1="paddingTop + ((i - 1) / 3) * plotHeight"
            :x2="svgWidth - paddingRight"
            :y2="paddingTop + ((i - 1) / 3) * plotHeight"
            stroke="currentColor"
            stroke-width="1"
            stroke-dasharray="3,3"
          />
          <!-- Y axis label texts -->
          <text
            v-for="i in 4"
            :key="`lbl-y-${i}`"
            :x="paddingLeft - 8"
            :y="paddingTop + ((i - 1) / 3) * plotHeight + 4"
            text-anchor="end"
            class="text-[9px] fill-neutral-muted font-mono"
          >
            {{ Math.round(maxVal - ((i - 1) / 3) * maxVal) }}
          </text>
        </g>

        <!-- 1. LINE CHART -->
        <g v-if="type === 'line'">
          <path
            :d="linePath"
            fill="none"
            :stroke="color"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- Points circles overlay -->
          <circle
            v-for="(pt, idx) in points"
            :key="`dot-${idx}`"
            :cx="pt.x"
            :cy="pt.y"
            :r="activeIndex === idx ? 6 : 4"
            :fill="activeIndex === idx ? '#ffdc83' : color"
            :stroke="color"
            stroke-width="2"
            class="transition-all duration-200 cursor-pointer"
            @mouseenter="activeIndex = idx"
            @mouseleave="activeIndex = null"
          />
        </g>

        <!-- 2. AREA CHART -->
        <g v-if="type === 'area'">
          <path
            :d="areaPath"
            fill="url(#area-grad)"
          />
          <path
            :d="linePath"
            fill="none"
            :stroke="color"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- Points overlay -->
          <circle
            v-for="(pt, idx) in points"
            :key="`area-dot-${idx}`"
            :cx="pt.x"
            :cy="pt.y"
            :r="activeIndex === idx ? 6 : 4"
            :fill="activeIndex === idx ? '#ffdc83' : color"
            :stroke="color"
            stroke-width="2"
            class="transition-all duration-200 cursor-pointer"
            @mouseenter="activeIndex = idx"
            @mouseleave="activeIndex = null"
          />
        </g>

        <!-- 3. BAR CHART -->
        <g v-if="type === 'bar'">
          <rect
            v-for="(bar, idx) in bars"
            :key="`bar-${idx}`"
            :x="bar.x"
            :y="bar.y"
            :width="bar.width"
            :height="bar.height"
            fill="url(#bar-grad)"
            rx="3"
            class="transition-opacity duration-200 cursor-pointer"
            :opacity="activeIndex === idx ? 1.0 : 0.85"
            @mouseenter="activeIndex = idx"
            @mouseleave="activeIndex = null"
          />
        </g>

        <!-- 4. PIE CHART -->
        <g v-if="type === 'pie'">
          <path
            v-for="(slice, idx) in pieSlices"
            :key="`pie-${idx}`"
            :d="slice.path"
            :fill="[color, '#ebe8de', '#ffdc83', '#b02e32', '#065f46'][idx % 5]"
            class="transition-all duration-300 cursor-pointer origin-center"
            :class="[activeIndex === idx ? 'scale-105' : '']"
            @mouseenter="activeIndex = idx"
            @mouseleave="activeIndex = null"
          />
          <!-- Labels drawn directly on sections -->
          <text
            v-for="(slice, idx) in pieSlices"
            :key="`pie-lbl-${idx}`"
            :x="slice.labelX"
            :y="slice.labelY"
            text-anchor="middle"
            class="text-[9px] font-bold fill-neutral-black pointer-events-none"
          >
            {{ slice.percentage }}%
          </text>
        </g>

        <!-- Bottom X Labels (For line, area, bar) -->
        <g v-if="type !== 'pie'" class="text-neutral-muted">
          <!-- Line & Area X labels -->
          <template v-if="type !== 'bar'">
            <text
              v-for="(pt, idx) in points"
              :key="`lbl-x-${idx}`"
              :x="pt.x"
              :y="paddingTop + plotHeight + 18"
              text-anchor="middle"
              class="text-[9px] fill-neutral-muted"
            >
              {{ pt.label }}
            </text>
          </template>
          <!-- Bar X labels -->
          <template v-else>
            <text
              v-for="(bar, idx) in bars"
              :key="`bar-lbl-x-${idx}`"
              :x="bar.x + bar.width / 2"
              :y="paddingTop + plotHeight + 18"
              text-anchor="middle"
              class="text-[9px] fill-neutral-muted"
            >
              {{ bar.label }}
            </text>
          </template>
        </g>

      </svg>
    </div>

    <!-- Chart Legend (specifically helpful for Pie charts) -->
    <div v-if="type === 'pie'" class="mt-4 flex flex-wrap justify-center gap-4 flex-shrink-0 border-t border-neutral-ivory/30 pt-3">
      <div
        v-for="(item, idx) in data"
        :key="`legend-${idx}`"
        class="flex items-center gap-2 text-xs"
        @mouseenter="activeIndex = idx"
        @mouseleave="activeIndex = null"
        :class="{ 'opacity-100 font-bold': activeIndex === idx, 'opacity-70': activeIndex !== null && activeIndex !== idx }"
      >
        <span
          class="h-2.5 w-2.5 rounded-full"
          :style="{ backgroundColor: [color, '#ebe8de', '#ffdc83', '#b02e32', '#065f46'][idx % 5] }"
        ></span>
        <span class="text-neutral-black">{{ item.label }} ({{ item.value }})</span>
      </div>
    </div>

  </div>
</template>
