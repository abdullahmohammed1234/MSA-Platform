<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-bold text-slate-900">Governance & Continuity Readiness</h2>
          <span
            class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
            :class="levelBadgeClasses"
          >
            {{ level }}
          </span>
        </div>
        <p class="text-sm text-slate-500 mt-1">
          Deterministic platform governance, data integrity, and operational recovery score.
        </p>
      </div>

      <!-- Score Circular Gauge / Badge -->
      <div class="flex items-center gap-3">
        <div class="text-right">
          <div class="text-3xl font-extrabold" :class="scoreColorClass">
            {{ score }}<span class="text-lg font-medium text-slate-400">/100</span>
          </div>
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Readiness Rating</div>
        </div>
      </div>
    </div>

    <!-- Score Drivers List -->
    <div v-if="drivers.length > 0" class="mt-6 pt-4 border-t border-slate-100">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Primary Score Drivers</h3>
      <div class="space-y-2">
        <div
          v-for="(driver, index) in drivers"
          :key="index"
          class="flex items-start gap-2 text-xs bg-slate-50 p-2.5 rounded-lg border border-slate-100"
        >
          <span class="px-1.5 py-0.5 bg-red-100 text-red-700 font-bold rounded text-[10px]">
            -{{ driver.deduction }} pts
          </span>
          <span class="text-slate-700 font-medium">{{ driver.reason }}</span>
        </div>
      </div>
    </div>
    <div v-else class="mt-4 pt-4 border-t border-slate-100">
      <p class="text-xs text-emerald-600 font-medium flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Optimal Readiness Score — No negative score drivers detected.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ScoreDriver } from '@/services/governanceService';

const props = defineProps<{
  score: number;
  level: 'EXCELLENT' | 'GOOD' | 'NEEDS_ATTENTION' | 'CRITICAL';
  drivers: ScoreDriver[];
}>();

const levelBadgeClasses = computed(() => {
  switch (props.level) {
    case 'EXCELLENT':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    case 'GOOD':
      return 'bg-blue-100 text-blue-800 border border-blue-200';
    case 'NEEDS_ATTENTION':
      return 'bg-amber-100 text-amber-800 border border-amber-200';
    case 'CRITICAL':
      return 'bg-rose-100 text-rose-800 border border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800';
  }
});

const scoreColorClass = computed(() => {
  if (props.score >= 90) return 'text-emerald-600';
  if (props.score >= 75) return 'text-blue-600';
  if (props.score >= 50) return 'text-amber-600';
  return 'text-rose-600';
});
</script>
