<script setup lang="ts">
import { computed } from 'vue';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';

const props = withDefaults(
  defineProps<{
    name: string;
    color?: string | null;
    size?: 'sm' | 'md';
  }>(),
  { size: 'sm' }
);

const { categoryTintStyle } = useEventFormatting();
const style = computed(() => categoryTintStyle(props.color));
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border font-semibold uppercase tracking-wider whitespace-nowrap"
    :class="size === 'sm' ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1'"
    :style="style"
  >
    <span
      class="h-1.5 w-1.5 shrink-0 rounded-full"
      :style="{ backgroundColor: style.color }"
      aria-hidden="true"
    />
    {{ name }}
  </span>
</template>
