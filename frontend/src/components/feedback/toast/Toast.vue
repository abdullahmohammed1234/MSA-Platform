<script setup lang="ts">
import { computed } from 'vue';
import type { ToastItem } from './types';
import { useToastStore } from './toastStore';

const props = defineProps<{
  toast: ToastItem;
}>();

const store = useToastStore();

const typeClasses = computed(() => {
  const types = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error: 'bg-red-50 border-red-200 text-secondary', // red
    warning: 'bg-amber-50 border-amber-200 text-amber-800',
    info: 'bg-sky-50 border-sky-200 text-sky-800'
  };
  return types[props.toast.type || 'info'];
});

const dismiss = () => {
  store.removeToast(props.toast.id);
};
</script>

<template>
  <div
    :class="[
      'flex items-center justify-between gap-3 p-4 border rounded-xl shadow-soft max-w-sm w-full bg-white select-none transition-all duration-300',
      typeClasses
    ]"
  >
    <!-- Icon representing status type -->
    <div class="flex-shrink-0">
      <!-- Success -->
      <svg v-if="toast.type === 'success'" class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <!-- Error -->
      <svg v-else-if="toast.type === 'error'" class="h-5 w-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <!-- Warning -->
      <svg v-else-if="toast.type === 'warning'" class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <!-- Info -->
      <svg v-else class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>

    <!-- Message text label -->
    <div class="flex-1 text-sm font-medium">
      {{ toast.message }}
    </div>

    <!-- Close button -->
    <button
      @click="dismiss"
      class="text-neutral-muted hover:text-neutral-black p-0.5 rounded transition-colors cursor-pointer"
      aria-label="Dismiss toast"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
