<script setup lang="ts">
import { Motion } from '@motionone/vue';
import { Sparkles, X } from 'lucide-vue-next';
import { useGamificationStore } from '@/stores/gamification';

const gamification = useGamificationStore();
</script>

<template>
  <Teleport to="body">
    <div
      v-if="gamification.celebration"
      class="fixed inset-0 z-[120] flex items-center justify-center bg-primary/40 backdrop-blur-sm px-6"
    >
      <Motion
        :initial="{ opacity: 0, scale: 0.92, y: 16 }"
        :animate="{ opacity: 1, scale: 1, y: 0 }"
        :transition="{ duration: 0.35 }"
        class="relative w-full max-w-md rounded-[2rem] border border-accent-gold/30 bg-white p-8 text-center shadow-premium-lg"
      >
        <button
          type="button"
          class="absolute right-4 top-4 rounded-full p-1 text-neutral-muted hover:text-primary"
          aria-label="Close celebration"
          @click="gamification.clearCelebration()"
        >
          <X class="h-4 w-4" />
        </button>

        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-gold/20 text-primary">
          <Sparkles class="h-7 w-7" />
        </div>

        <h2 class="text-2xl font-display font-bold text-primary">{{ gamification.celebration.title }}</h2>
        <p class="mt-3 text-sm leading-6 text-neutral-muted">{{ gamification.celebration.subtitle }}</p>

        <p v-if="gamification.celebration.xpBonus" class="mt-4 text-xs font-bold uppercase tracking-[0.2em] text-accent-gold">
          +{{ gamification.celebration.xpBonus }} XP
        </p>

        <button
          type="button"
          class="mt-6 rounded-full bg-primary px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-white"
          @click="gamification.clearCelebration()"
        >
          Continue Learning
        </button>
      </Motion>
    </div>

    <div class="fixed top-24 right-6 z-[110] space-y-3 pointer-events-none">
      <div
        v-for="toast in gamification.toasts"
        :key="toast.id"
        class="pointer-events-auto min-w-[260px] rounded-2xl border border-neutral-ivory bg-white px-4 py-3 shadow-premium"
      >
        <p class="text-xs font-bold uppercase tracking-wider text-primary">{{ toast.title }}</p>
        <p class="mt-1 text-sm text-neutral-muted">{{ toast.subtitle }}</p>
      </div>
    </div>
  </Teleport>
</template>
