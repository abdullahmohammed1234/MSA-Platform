<script setup lang="ts">
import type { LoaderProps } from './types';

const props = withDefaults(defineProps<LoaderProps>(), {
  type: 'spinner',
  label: '',
  color: 'text-primary'
});
</script>

<template>
  <!-- Full Screen Loader Overlay -->
  <div
    v-if="type === 'fullscreen'"
    class="fixed inset-0 z-[999] bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center gap-4 select-none"
  >
    <div class="relative flex items-center justify-center">
      <!-- Spinning Outer Ring -->
      <svg
        :class="['animate-spin h-12 w-12', color]"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <!-- Center stationary logo dot -->
      <div class="absolute h-4 w-4 bg-accent-gold rounded-full"></div>
    </div>
    <span v-if="label" class="text-sm font-semibold text-neutral-black tracking-wide font-display">
      {{ label }}
    </span>
  </div>

  <!-- Standard Inline dot spinner loader -->
  <div v-else-if="type === 'inline'" class="inline-flex items-center gap-2 select-none">
    <svg :class="['animate-spin h-4 w-4', color]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span v-if="label" class="text-xs text-neutral-muted font-medium">{{ label }}</span>
  </div>

  <!-- Regular Spinner widget -->
  <div v-else class="flex flex-col items-center justify-center gap-2 p-6 select-none">
    <svg :class="['animate-spin h-8 w-8', color]" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span v-if="label" class="text-xs text-neutral-muted font-medium">{{ label }}</span>
  </div>
</template>
