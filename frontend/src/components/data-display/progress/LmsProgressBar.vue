<script setup lang="ts">
import { computed } from 'vue';
import type { ProgressBarProps } from './types';

const props = withDefaults(defineProps<ProgressBarProps>(), {
  color: 'bg-primary',
  showLabel: false
});

const percentage = computed(() => {
  return Math.min(Math.max(props.value, 0), 100);
});
</script>

<template>
  <div class="w-full">
    <!-- Header with label & percentage -->
    <div v-if="showLabel" class="flex justify-between items-center mb-1">
      <slot name="label">
        <span class="text-xs font-semibold text-neutral-muted">Progress</span>
      </slot>
      <span class="text-xs font-bold text-primary">{{ percentage }}%</span>
    </div>
    
    <!-- Track container -->
    <div class="h-2 bg-neutral-ivory/50 rounded-full border border-neutral-ivory/30 overflow-hidden w-full">
      <!-- Colored dynamic progress line -->
      <div
        :class="['h-full rounded-full transition-all duration-700 ease-out', color]"
        :style="{ width: `${percentage}%` }"
      />
    </div>
  </div>
</template>
