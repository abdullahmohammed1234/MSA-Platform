<script setup lang="ts">
import { computed } from 'vue';
import LmsProgressBar from '@/components/data-display/progress/LmsProgressBar.vue';

const props = defineProps<{
  completedCount: number;
  totalCount: number;
  courseTitle: string;
}>();

const progress = computed(() => {
  if (props.totalCount <= 0) return 0;
  return Math.round((props.completedCount / props.totalCount) * 100);
});
</script>

<template>
  <div class="rounded-2xl border border-neutral-ivory bg-neutral-background/60 p-4 space-y-2">
    <div class="flex items-center justify-between text-xs">
      <span class="font-semibold text-primary">{{ courseTitle }}</span>
      <span class="font-mono text-neutral-muted">{{ completedCount }}/{{ totalCount }} items</span>
    </div>
    <LmsProgressBar :value="progress" color="bg-primary" />
  </div>
</template>
