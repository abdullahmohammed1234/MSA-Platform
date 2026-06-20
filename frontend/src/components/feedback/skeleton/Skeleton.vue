<script setup lang="ts">
import type { SkeletonProps } from './types';

const props = withDefaults(defineProps<SkeletonProps>(), {
  type: 'text'
});
</script>

<template>
  <!-- 1. Text paragraph lines -->
  <div v-if="type === 'text'" class="space-y-2.5 animate-pulse w-full select-none">
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-full"></div>
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-11/12"></div>
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-4/5"></div>
  </div>

  <!-- 2. Course Card block -->
  <div v-else-if="type === 'card'" class="border border-neutral-ivory rounded-2xl p-4 space-y-4 bg-white animate-pulse w-full select-none">
    <!-- Cover image shape -->
    <div class="h-40 bg-neutral-ivory/70 rounded-xl w-full"></div>
    <div class="space-y-2.5">
      <!-- Title -->
      <div class="h-4.5 bg-neutral-ivory/70 rounded-md w-2/3"></div>
      <!-- Description -->
      <div class="h-3.5 bg-neutral-ivory/70 rounded-md w-full"></div>
      <div class="h-3.5 bg-neutral-ivory/70 rounded-md w-5/6"></div>
    </div>
    <!-- Footer/Avatar shape -->
    <div class="flex items-center gap-3 pt-2">
      <div class="h-8 w-8 rounded-full bg-neutral-ivory/70"></div>
      <div class="h-3.5 bg-neutral-ivory/70 rounded-md w-1/3"></div>
    </div>
  </div>

  <!-- 3. Avatar shape -->
  <div v-else-if="type === 'avatar'" class="animate-pulse select-none">
    <div class="h-10 w-10 bg-neutral-ivory/70 rounded-full"></div>
  </div>

  <!-- 4. Table Row line -->
  <div v-else-if="type === 'table-row'" class="flex items-center justify-between gap-6 py-4 animate-pulse w-full border-b border-neutral-ivory/40 select-none">
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-1/4"></div>
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-1/3"></div>
    <div class="h-4 bg-neutral-ivory/70 rounded-md w-1/6"></div>
  </div>
</template>
