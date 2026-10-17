<template>
  <div class="text-center py-10 px-6 rounded-2xl border border-red-200 bg-red-50/30" role="alert">
    <div class="w-12 h-12 mx-auto rounded-full bg-red-100 flex items-center justify-center text-red-600 mb-4">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
    </div>
    <h4 class="text-base font-semibold text-red-800">
      {{ title }}
    </h4>
    <p v-if="message" class="text-xs text-red-600/90 max-w-sm mx-auto mt-1 leading-relaxed">
      {{ message }}
    </p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action"></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string;
    message?: string;
  }>(),
  {
    title: 'Unable to load content',
  },
);
</script>
