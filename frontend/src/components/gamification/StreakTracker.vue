<script setup lang="ts">
import { Flame } from 'lucide-vue-next';
import { useGamificationStore } from '@/stores/gamification';

defineProps<{
  label?: string;
}>();

const gamification = useGamificationStore();
</script>

<template>
  <div class="rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-50 to-amber-50 p-4 flex items-center gap-4">
    <div class="h-12 w-12 rounded-xl bg-orange-500/15 text-orange-600 flex items-center justify-center">
      <Flame class="h-6 w-6" />
    </div>
    <div>
      <p class="text-[10px] font-bold uppercase tracking-widest text-orange-700/80">
        {{ label || 'Learning streak' }}
      </p>
      <p class="text-2xl font-display font-bold text-primary">
        {{ gamification.streakDays }} <span class="text-sm font-sans font-medium text-neutral-muted">days</span>
      </p>
    </div>
  </div>
</template>
