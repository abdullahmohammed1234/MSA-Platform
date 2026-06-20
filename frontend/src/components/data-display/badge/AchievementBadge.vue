<script setup lang="ts">
import type { AchievementBadgeProps } from './types';
import Badge from './Badge.vue';

const props = withDefaults(defineProps<AchievementBadgeProps>(), {
  xp: 100,
  isLocked: false
});
</script>

<template>
  <div
    :class="[
      'flex items-center gap-4 p-4 border border-neutral-ivory rounded-2xl transition-all duration-300 bg-white shadow-soft w-full',
      isLocked ? 'filter grayscale opacity-60 bg-neutral-background/30' : 'hover:shadow-premium-md'
    ]"
  >
    <!-- Dashed Hexagon/Box Seal -->
    <div
      class="h-12 w-12 border-2 border-dashed border-accent-gold rounded-xl flex items-center justify-center flex-shrink-0 transition-transform duration-500 ease-out transform hover:rotate-12 cursor-pointer bg-accent-gold/5"
    >
      <slot name="icon">
        <!-- Default scholastic book/medal icon -->
        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </slot>
    </div>

    <!-- Details -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center justify-between gap-2 mb-1">
        <h4 class="text-sm font-semibold text-neutral-black truncate leading-none">
          {{ title }}
        </h4>
        <Badge variant="gold" size="sm" class="flex-shrink-0">
          +{{ xp }} XP
        </Badge>
      </div>
      <p class="text-xs text-neutral-muted line-clamp-2 leading-tight mb-1">
        {{ description }}
      </p>
      <div class="text-[10px] text-neutral-muted/70 font-semibold uppercase tracking-wider leading-none">
        <span v-if="isLocked">Locked</span>
        <span v-else-if="unlockedAt">Earned {{ unlockedAt }}</span>
        <span v-else>Unlocked</span>
      </div>
    </div>
  </div>
</template>
