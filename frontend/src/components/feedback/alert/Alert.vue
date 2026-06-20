<script setup lang="ts">
import { ref, computed } from 'vue';
import type { AlertProps } from './types';

const props = withDefaults(defineProps<AlertProps>(), {
  type: 'info',
  closable: false
});

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const isVisible = ref(true);

const typeClasses = computed(() => {
  const types = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-900',
    error: 'bg-red-50 border-red-200 text-[#b02e32]',
    warning: 'bg-amber-50 border-amber-200 text-amber-900',
    info: 'bg-sky-50 border-sky-200 text-sky-900'
  };
  return types[props.type];
});

const close = () => {
  isVisible.value = false;
  emit('close');
};
</script>

<template>
  <div
    v-if="isVisible"
    :class="[
      'flex gap-3 p-4 border rounded-xl relative select-none w-full shadow-sm',
      typeClasses
    ]"
    role="alert"
  >
    <!-- Icon representation based on status -->
    <div class="flex-shrink-0 mt-0.5">
      <!-- Success -->
      <svg v-if="type === 'success'" class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <!-- Error -->
      <svg v-else-if="type === 'error'" class="h-5 w-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <!-- Warning -->
      <svg v-else-if="type === 'warning'" class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <!-- Info -->
      <svg v-else class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>

    <!-- Content details -->
    <div class="flex-1 min-w-0">
      <h4 v-if="title" class="text-sm font-semibold leading-none mb-1 text-neutral-black">
        {{ title }}
      </h4>
      <div class="text-xs leading-relaxed text-neutral-muted">
        <slot></slot>
      </div>
      <!-- Actions slot -->
      <div v-if="$slots.actions" class="mt-2.5 flex items-center gap-3">
        <slot name="actions"></slot>
      </div>
    </div>

    <!-- Close button -->
    <button
      v-if="closable"
      @click="close"
      class="text-neutral-muted hover:text-neutral-black p-0.5 rounded transition-colors cursor-pointer self-start"
      aria-label="Close alert"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
