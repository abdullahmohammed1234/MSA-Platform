<template>
  <div class="text-center py-12 px-6 rounded-2xl border border-dashed border-[#ebe8de] bg-white/40">
    <div class="w-12 h-12 mx-auto rounded-full bg-[#ebe8de]/60 flex items-center justify-center text-[#640c0e]/70 mb-4">
      <slot name="icon">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
      </slot>
    </div>
    <h4 class="text-base font-semibold text-[#640c0e]">
      {{ title }}
    </h4>
    <p v-if="description" class="text-xs text-neutral-500 max-w-sm mx-auto mt-1 leading-relaxed">
      {{ description }}
    </p>
    <div v-if="$slots.action" class="mt-5">
      <slot name="action"></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string;
  description?: string;
}>();
</script>
