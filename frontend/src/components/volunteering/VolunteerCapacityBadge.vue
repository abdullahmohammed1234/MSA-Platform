<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  status?: string;
  capacity?: number | null;
  signupsCount?: number;
  compact?: boolean;
}>();

const badgeConfig = computed(() => {
  const statusLower = (props.status || 'open').toLowerCase();

  if (statusLower === 'closed') {
    return {
      label: 'Closed',
      classes: 'bg-neutral-gray/20 text-neutral-muted border-neutral-gray/30',
    };
  }

  if (statusLower === 'waitlist' || statusLower === 'waitlisted') {
    return {
      label: 'Waitlist Open',
      classes: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
    };
  }

  if (props.capacity !== null && props.capacity !== undefined && props.capacity > 0) {
    const current = props.signupsCount || 0;
    const remaining = props.capacity - current;

    if (remaining <= 0) {
      return {
        label: 'Full (Waitlist)',
        classes: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
      };
    } else if (remaining <= 3) {
      return {
        label: `Almost Full (${remaining} left)`,
        classes: 'bg-secondary/10 text-secondary border-secondary/30',
      };
    } else {
      return {
        label: `${remaining} Spots Open`,
        classes: 'bg-emerald-600/10 text-emerald-800 border-emerald-600/30',
      };
    }
  }

  return {
    label: 'Open Opportunities',
    classes: 'bg-emerald-600/10 text-emerald-800 border-emerald-600/30',
  };
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 font-bold uppercase tracking-wider rounded-full border transition-colors',
      compact ? 'px-2.5 py-0.5 text-[10px]' : 'px-3 py-1 text-xs',
      badgeConfig.classes
    ]"
  >
    <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0" aria-hidden="true" />
    {{ badgeConfig.label }}
  </span>
</template>
