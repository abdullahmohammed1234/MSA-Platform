<script setup lang="ts">
import { computed } from 'vue';
import type { CircularProgressProps } from './types';

const props = withDefaults(defineProps<CircularProgressProps>(), {
  size: 60,
  strokeWidth: 5,
  color: 'text-primary'
});

const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const strokeDashoffset = computed(() => {
  const percentage = Math.min(Math.max(props.value, 0), 100);
  return circumference.value * (1 - percentage / 100);
});
</script>

<template>
  <div class="relative inline-flex items-center justify-center select-none">
    <svg :width="size" :height="size" class="transform -rotate-90">
      <!-- Background Circle track -->
      <circle
        class="text-neutral-ivory/50"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        fill="transparent"
        :r="radius"
        :cx="size / 2"
        :cy="size / 2"
      />
      <!-- Active Progress Circle segment -->
      <circle
        :class="[color, 'transition-all duration-700 ease-out']"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        fill="transparent"
        :r="radius"
        :cx="size / 2"
        :cy="size / 2"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="strokeDashoffset"
      />
    </svg>
    
    <!-- Text Display inside center -->
    <div class="absolute text-[10px] sm:text-xs font-bold text-neutral-black">
      {{ Math.round(value) }}%
    </div>
  </div>
</template>
