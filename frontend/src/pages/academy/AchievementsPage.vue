<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useAchievementsStore } from '@/stores/academy/achievements';

const achievementsStore = useAchievementsStore();
const activeFilter = ref<'all' | 'unlocked' | 'locked'>('all');

onMounted(async () => {
  await achievementsStore.fetchAchievements();
});

const filteredAchievements = computed(() => {
  if (activeFilter.value === 'unlocked') {
    return achievementsStore.achievements.filter(a => a.unlocked);
  }
  if (activeFilter.value === 'locked') {
    return achievementsStore.achievements.filter(a => !a.unlocked);
  }
  return achievementsStore.achievements;
});

const totalCount = computed(() => achievementsStore.achievements.length);
const unlockedCount = computed(() => achievementsStore.achievements.filter(a => a.unlocked).length);
const completionPercentage = computed(() => {
  if (totalCount.value === 0) return 0;
  return Math.round((unlockedCount.value / totalCount.value) * 100);
});

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};

const getAchievementIconClass = (type: string, unlocked: boolean) => {
  if (!unlocked) return 'bg-neutral-background text-neutral-muted border-neutral-ivory/80';
  
  switch (type) {
    case 'completion':
      return 'bg-emerald-50 text-emerald-600 border-emerald-200';
    case 'performance':
      return 'bg-accent-gold/10 text-primary border-accent-gold/30';
    case 'participation':
      return 'bg-secondary/10 text-secondary border-secondary/20';
    default:
      return 'bg-blue-50 text-blue-600 border-blue-200';
  }
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <!-- Hero Header section -->
    <div class="bg-gradient-to-br from-primary via-primary-dark to-neutral-black p-8 rounded-3xl text-white shadow-premium relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8">
      <!-- Decorative background blur -->
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-secondary/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-accent-gold/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="space-y-3 relative z-10 text-center md:text-left">
        <span class="bg-accent-gold/20 text-accent-gold text-[10px] font-bold tracking-widest uppercase px-3 py-1 rounded-full border border-accent-gold/30">
          Academy Achievements
        </span>
        <h1 class="text-3xl sm:text-4xl font-display font-bold tracking-tight">Your Scholar Journey</h1>
        <p class="text-neutral-ivory/80 text-sm max-w-md font-light leading-relaxed">
          Complete academy lectures, pass final quizzes, and maintain high scores to unlock custom achievements and earn points.
        </p>
      </div>

      <!-- Point Counter Badge -->
      <div class="flex flex-col items-center justify-center bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-2xl w-full sm:w-auto min-w-[200px] shadow-soft relative z-10">
        <span class="text-xs font-mono font-bold tracking-widest uppercase text-accent-gold">Total Score</span>
        <span class="text-5xl font-display font-extrabold text-white mt-1 drop-shadow-sm">{{ achievementsStore.points }}</span>
        <span class="text-[10px] text-neutral-ivory/70 mt-1 font-mono font-semibold">Scholar Points (SP)</span>
      </div>
    </div>

    <!-- Progress Overview Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Total Unlocked Card -->
      <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft flex items-center gap-4">
        <div class="h-12 w-12 bg-secondary/15 text-secondary border border-secondary/25 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <span class="text-[10px] text-neutral-muted font-bold tracking-wider uppercase">Achievements Unlocked</span>
          <h3 class="text-2xl font-display font-bold text-primary mt-0.5">{{ unlockedCount }} / {{ totalCount }}</h3>
        </div>
      </div>

      <!-- Completion Progress Card -->
      <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft md:col-span-2 flex flex-col justify-center gap-2">
        <div class="flex justify-between items-center text-[10px] font-bold text-neutral-muted font-mono tracking-wide">
          <span>ACADEMY COMPLETION</span>
          <span class="text-secondary font-extrabold">{{ completionPercentage }}%</span>
        </div>
        <div class="relative w-full h-3 bg-neutral-ivory rounded-full overflow-hidden">
          <div
            class="h-full bg-secondary rounded-full transition-all duration-500 ease-out"
            :style="{ width: `${completionPercentage}%` }"
          ></div>
        </div>
        <p class="text-[9px] text-neutral-muted font-light mt-0.5">
          Progress is based on active achievement points unlocked through academy activities.
        </p>
      </div>
    </div>

    <!-- Filters & Achievements List -->
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h2 class="text-xl font-display font-semibold text-primary">All Accomplishments</h2>
        
        <!-- Filter Tabs -->
        <div class="flex items-center bg-neutral-ivory/60 p-1 rounded-xl border border-neutral-ivory/60">
          <button
            v-for="filter in (['all', 'unlocked', 'locked'] as const)"
            :key="filter"
            @click="activeFilter = filter"
            class="px-4 py-1.5 rounded-lg text-xs font-bold capitalize transition-all duration-300"
            :class="activeFilter === filter ? 'bg-white text-primary shadow-soft' : 'text-neutral-muted hover:text-neutral-black'"
          >
            {{ filter }}
          </button>
        </div>
      </div>

      <!-- Grid of Achievements -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="ach in filteredAchievements"
          :key="ach.id"
          class="bg-white border p-6 rounded-3xl shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col justify-between relative overflow-hidden"
          :class="[
            ach.unlocked ? 'border-neutral-ivory/80' : 'border-neutral-ivory/40 opacity-70'
          ]"
        >
          <!-- Shiny unlocked banner -->
          <div v-if="ach.unlocked" class="absolute -right-12 -top-12 w-24 h-24 bg-secondary/5 rounded-full pointer-events-none"></div>

          <div class="space-y-4">
            <!-- Icon and Badge Points -->
            <div class="flex justify-between items-start">
              <div
                class="h-12 w-12 rounded-2xl border flex items-center justify-center flex-shrink-0 transition-colors"
                :class="getAchievementIconClass(ach.type, ach.unlocked)"
              >
                <!-- Trophy icon -->
                <svg v-if="ach.unlocked" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm0 0h4m-4 0H8m12 3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <!-- Locked lock icon -->
                <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>

              <span
                class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg border transition-all"
                :class="ach.unlocked ? 'bg-accent-gold/10 text-primary border-accent-gold/20' : 'bg-neutral-background text-neutral-muted border-neutral-ivory'"
              >
                +{{ ach.points }} SP
              </span>
            </div>

            <!-- Title & Description -->
            <div class="space-y-1">
              <h3 class="font-display font-semibold text-base leading-snug" :class="ach.unlocked ? 'text-primary' : 'text-neutral-black/70'">
                {{ ach.title }}
              </h3>
              <p class="text-xs font-light leading-relaxed" :class="ach.unlocked ? 'text-neutral-muted' : 'text-neutral-muted/60'">
                {{ ach.description }}
              </p>
            </div>
          </div>

          <!-- Unlock Footer status -->
          <div class="mt-6 pt-4 border-t border-neutral-ivory/50 flex justify-between items-center text-[10px] font-mono">
            <span class="text-neutral-muted uppercase tracking-wider font-bold">Category: {{ ach.type }}</span>
            <span v-if="ach.unlocked" class="text-secondary font-bold">
              Unlocked {{ formatDate(ach.unlocked_at) }}
            </span>
            <span v-else class="text-neutral-muted font-semibold">
              Locked
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="filteredAchievements.length === 0"
        class="bg-white border border-neutral-ivory rounded-3xl p-12 text-center shadow-soft"
      >
        <svg class="mx-auto h-12 w-12 text-neutral-gray/50 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <h3 class="text-base font-display font-semibold text-primary">No Achievements Found</h3>
        <p class="text-neutral-muted text-xs mt-1 max-w-sm mx-auto font-light leading-relaxed">
          No achievements fit the current filter criteria.
        </p>
      </div>
    </div>
  </div>
</template>
