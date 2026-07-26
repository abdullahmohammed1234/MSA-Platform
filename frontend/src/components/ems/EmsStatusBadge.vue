<script setup lang="ts">
import { computed } from 'vue';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EventStatusTone } from '@/types/ems';

/**
 * An event's lifecycle state.
 *
 * Both the label and the tone come from the API, so the seven states are
 * described in exactly one place.
 */
const props = withDefaults(
  defineProps<{
    label: string;
    tone: EventStatusTone;
    size?: 'sm' | 'md';
  }>(),
  { size: 'md' }
);

const { dotClass } = useEventFormatting();

const toneClasses: Record<EventStatusTone, string> = {
  neutral: 'bg-neutral-ivory/60 text-neutral-muted border-neutral-ivory',
  info: 'bg-sky-50 text-sky-800 border-sky-200',
  success: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  warning: 'bg-amber-50 text-amber-800 border-amber-200',
  live: 'bg-red-50 text-secondary border-red-200',
  muted: 'bg-neutral-background text-neutral-muted border-neutral-ivory',
  danger: 'bg-red-50 text-red-800 border-red-200',
};

const classes = computed(() => [
  'inline-flex items-center gap-1.5 rounded-full border font-semibold whitespace-nowrap',
  props.size === 'sm' ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1',
  toneClasses[props.tone] ?? toneClasses.neutral,
]);
</script>

<template>
  <span :class="classes">
    <span
      class="h-1.5 w-1.5 rounded-full shrink-0"
      :class="[dotClass(tone), tone === 'live' ? 'animate-pulse' : '']"
      aria-hidden="true"
    />
    {{ label }}
  </span>
</template>
