<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  content?: string | null;
  title: string;
}>();

const paragraphs = computed(() => {
  if (!props.content) return [];
  return props.content
    .split(/\n{2,}/)
    .map((block) => block.trim())
    .filter(Boolean);
});
</script>

<template>
  <div class="space-y-4">
    <div>
      <h3 class="text-sm font-semibold text-primary">Lesson Transcript</h3>
      <p class="text-xs text-neutral-muted mt-1">Full reading text for {{ title }}</p>
    </div>

    <div v-if="paragraphs.length === 0" class="text-sm text-neutral-muted italic">
      No transcript available for this lesson.
    </div>

    <div v-else class="space-y-4 max-h-[28rem] overflow-y-auto pr-2">
      <p
        v-for="(paragraph, index) in paragraphs"
        :key="index"
        class="text-sm leading-relaxed text-neutral-black/80 font-light"
      >
        {{ paragraph.replace(/\n/g, ' ') }}
      </p>
    </div>
  </div>
</template>
