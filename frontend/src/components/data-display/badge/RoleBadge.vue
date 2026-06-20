<script setup lang="ts">
import { computed } from 'vue';
import type { RoleBadgeProps } from './types';

const props = defineProps<RoleBadgeProps>();

const roleDetails = computed(() => {
  const details = {
    mujtahid: {
      initials: 'M',
      title: 'Mujtahid',
      arabic: 'مجتهد',
      description: 'Independent Legal Jurist',
      badgeColor: 'gold' as const,
      avatarBg: 'bg-accent-gold/15 text-primary border-accent-gold/30'
    },
    alim: {
      initials: 'A',
      title: 'Alim',
      arabic: 'عالم',
      description: 'Religious Scholar / Educator',
      badgeColor: 'primary' as const,
      avatarBg: 'bg-primary/10 text-primary border-primary/20'
    },
    murshid: {
      initials: 'M',
      title: 'Murshid',
      arabic: 'مرشد',
      description: 'Spiritual Guide / Advisor',
      badgeColor: 'success' as const,
      avatarBg: 'bg-emerald-100 text-emerald-800 border-emerald-200'
    },
    mutallim: {
      initials: 'M',
      title: 'Mutallim',
      arabic: 'متعلم',
      description: 'Active Seeker of Knowledge',
      badgeColor: 'outline' as const,
      avatarBg: 'bg-neutral-ivory/50 text-neutral-muted border-neutral-ivory'
    }
  };

  return details[props.role];
});
</script>

<template>
  <div class="flex items-center gap-3 p-3 border border-neutral-ivory rounded-xl bg-white/50 w-full">
    <!-- Circle Initials avatar -->
    <div
      :class="[
        'h-10 w-10 rounded-full border flex items-center justify-center font-display font-bold text-base flex-shrink-0',
        roleDetails.avatarBg
      ]"
    >
      {{ roleDetails.initials }}
    </div>

    <!-- Rank label titles -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold text-neutral-black truncate leading-none">
          {{ title || roleDetails.title }}
        </h4>
        <!-- Arabic calligraphy label -->
        <span
          class="font-serif text-sm font-bold text-primary px-1.5 py-0.5 bg-neutral-background rounded border border-neutral-ivory/40 leading-none"
          dir="rtl"
        >
          {{ roleDetails.arabic }}
        </span>
      </div>
      <p class="text-xs text-neutral-muted truncate leading-normal mt-1">
        {{ description || roleDetails.description }}
      </p>
    </div>
  </div>
</template>
