<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string;
    value: number;
    /** Shows a skeleton in place of the figure while the dashboard loads. */
    isLoading?: boolean;
    /** Navigates to a pre-filtered event list when set. */
    to?: string;
    accent?: 'default' | 'draft' | 'published' | 'upcoming' | 'completed';
  }>(),
  { isLoading: false, accent: 'default' }
);

const accentClasses: Record<string, string> = {
  default: 'text-primary',
  draft: 'text-neutral-muted',
  published: 'text-sky-700',
  upcoming: 'text-emerald-700',
  completed: 'text-neutral-muted',
};
</script>

<template>
  <component
    :is="to ? 'router-link' : 'div'"
    :to="to"
    class="block rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft transition-all"
    :class="to ? 'hover:border-primary/40 hover:shadow-premium-md focus-visible:outline-2 focus-visible:outline-primary' : ''"
  >
    <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">{{ label }}</p>

    <div
      v-if="isLoading"
      class="mt-2 h-8 w-16 rounded-md bg-neutral-ivory/70 animate-pulse"
      role="status"
      aria-label="Loading"
    />
    <p v-else class="mt-2 text-3xl font-bold tabular-nums" :class="accentClasses[accent]">
      {{ value }}
    </p>
  </component>
</template>
