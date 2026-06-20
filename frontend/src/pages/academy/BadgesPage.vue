<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useBadgesStore } from '@/stores/academy/badges';

const badgesStore = useBadgesStore();
const activeFilter = ref<'all' | 'unlocked' | 'locked'>('all');

onMounted(async () => {
  await badgesStore.fetchBadges();
});

const filteredBadges = computed(() => {
  if (activeFilter.value === 'unlocked') {
    return badgesStore.badges.filter(b => b.unlocked);
  }
  if (activeFilter.value === 'locked') {
    return badgesStore.badges.filter(b => !b.unlocked);
  }
  return badgesStore.badges;
});

const totalCount = computed(() => badgesStore.badges.length);
const unlockedCount = computed(() => badgesStore.badges.filter(b => b.unlocked).length);

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};

// Returns a premium CSS visual or inline SVG fallback based on the badge slug
const getBadgeVisual = (slug: string) => {
  switch (slug) {
    case 'first-course-completed':
      return {
        bg: 'from-emerald-400 to-teal-600 shadow-emerald-500/25',
        svg: `<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`
      };
    case 'quiz-master':
      return {
        bg: 'from-amber-400 to-orange-600 shadow-orange-500/25',
        svg: `<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`
      };
    case 'perfect-score':
      return {
        bg: 'from-rose-400 to-red-600 shadow-red-500/25',
        svg: `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 11l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>`
      };
    case 'consistent-learner':
      return {
        bg: 'from-blue-400 to-indigo-600 shadow-blue-500/25',
        svg: `<path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`
      };
    case 'volunteer-graduate':
      return {
        bg: 'from-violet-400 to-purple-600 shadow-purple-500/25',
        svg: `<path d="M22 10v6M2 10l10-5 10 5-10 5zM6 12v5c0 2 2.5 3 6 3s6-1 6-3v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`
      };
    default:
      return {
        bg: 'from-slate-400 to-slate-600 shadow-slate-500/25',
        svg: `<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M8 12h8M12 8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>`
      };
  }
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Honor Badges</h1>
        <p class="text-neutral-muted text-sm mt-1 font-light">
          Unlock unique honors for your Dawah efforts, quiz precision, and consistent study habits.
        </p>
      </div>

      <!-- Stats Summary -->
      <div class="bg-neutral-ivory/60 border border-neutral-ivory p-3.5 px-6 rounded-2xl flex items-center gap-4 self-stretch sm:self-auto font-mono text-xs font-bold text-neutral-muted">
        <div>
          <span class="block text-[9px] uppercase tracking-wider text-neutral-muted">Unlocked Badges</span>
          <span class="text-lg text-primary font-bold">{{ unlockedCount }} / {{ totalCount }}</span>
        </div>
        <div class="h-8 w-px bg-neutral-ivory/80"></div>
        <div>
          <span class="block text-[9px] uppercase tracking-wider text-neutral-muted">Rarity Score</span>
          <span class="text-lg text-secondary font-bold">
            {{ totalCount > 0 ? Math.round((unlockedCount / totalCount) * 100) : 0 }}% Completed
          </span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
      <h2 class="text-xl font-display font-semibold text-primary">Badge Catalog</h2>
      
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

    <!-- Badges Grid Layout -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <div
        v-for="badge in filteredBadges"
        :key="badge.id"
        class="bg-white border rounded-3xl p-6 shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col items-center text-center justify-between min-h-[300px] relative overflow-hidden"
        :class="badge.unlocked ? 'border-neutral-ivory/80' : 'border-neutral-ivory/40 opacity-70'"
      >
        <!-- Lock indicator overlay if locked -->
        <div v-if="!badge.unlocked" class="absolute top-4 right-4 text-neutral-muted/50">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <!-- Visual representation of the badge -->
        <div class="space-y-6 flex flex-col items-center w-full mt-4">
          <!-- Premium HSL styled badge shape -->
          <div
            class="h-28 w-28 rounded-full bg-gradient-to-tr p-1 flex items-center justify-center shadow-lg transform transition-transform duration-500 hover:rotate-12"
            :class="badge.unlocked ? getBadgeVisual(badge.slug).bg : 'from-neutral-background to-neutral-ivory shadow-none'"
          >
            <div class="h-full w-full rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center text-white border border-white/20">
              <svg
                class="h-12 w-12"
                :class="badge.unlocked ? 'text-white' : 'text-neutral-muted/40'"
                fill="none"
                viewBox="0 0 24 24"
                v-html="getBadgeVisual(badge.slug).svg"
              ></svg>
            </div>
          </div>

          <div class="space-y-1.5 px-2">
            <h3 class="font-display font-bold text-base text-primary leading-snug">
              {{ badge.name }}
            </h3>
            <p class="text-xs text-neutral-muted font-light leading-relaxed">
              {{ badge.description }}
            </p>
          </div>
        </div>

        <!-- Unlocking footer details -->
        <div class="w-full mt-6 pt-4 border-t border-neutral-ivory/50">
          <span
            v-if="badge.unlocked"
            class="text-[9px] font-mono font-bold text-secondary uppercase tracking-wider bg-secondary/15 px-3 py-1 rounded-full border border-secondary/20"
          >
            Unlocked {{ formatDate(badge.unlocked_at) }}
          </span>
          <div v-else class="space-y-1">
            <span class="text-[9px] font-mono font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background px-3 py-1 rounded-full border border-neutral-ivory/80">
              Criteria
            </span>
            <p class="text-[9px] text-neutral-muted/70 font-light font-mono mt-1">
              Type: {{ badge.criteria_type.replace('_', ' ') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="filteredBadges.length === 0"
      class="bg-white border border-neutral-ivory rounded-3xl p-12 text-center shadow-soft"
    >
      <svg class="mx-auto h-12 w-12 text-neutral-gray/50 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 9.172V5L8 4z" />
      </svg>
      <h3 class="text-base font-display font-semibold text-primary">No Badges Found</h3>
      <p class="text-neutral-muted text-xs mt-1 max-w-sm mx-auto font-light leading-relaxed">
        No badges meet the active filter. Complete more modules to discover new awards!
      </p>
    </div>
  </div>
</template>
