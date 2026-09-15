<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-slate-900">Continuity & Recovery Readiness</h3>
        <p class="text-xs text-slate-500">Infrastructure probes and truthful backup verification status</p>
      </div>
      <span
        class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
        :class="overallBadgeClass"
      >
        {{ readiness.overall_status }}
      </span>
    </div>

    <!-- Continuity Summary Banner -->
    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-medium mb-4">
      {{ readiness.continuity_summary }}
    </div>

    <!-- Infrastructure & Backup Probes List -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <!-- Database Probe -->
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
        <div class="flex items-center justify-between gap-2 mb-1">
          <span class="font-bold text-xs text-slate-900">{{ readiness.probes.database.name }}</span>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="probeStatusClass(readiness.probes.database.status)">
            {{ readiness.probes.database.status }}
          </span>
        </div>
        <p class="text-xs text-slate-600 font-medium">{{ readiness.probes.database.details }}</p>
      </div>

      <!-- Cache Store Probe -->
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
        <div class="flex items-center justify-between gap-2 mb-1">
          <span class="font-bold text-xs text-slate-900">{{ readiness.probes.cache.name }}</span>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="probeStatusClass(readiness.probes.cache.status)">
            {{ readiness.probes.cache.status }}
          </span>
        </div>
        <p class="text-xs text-slate-600 font-medium">{{ readiness.probes.cache.details }}</p>
      </div>

      <!-- Queue System Probe -->
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
        <div class="flex items-center justify-between gap-2 mb-1">
          <span class="font-bold text-xs text-slate-900">{{ readiness.probes.queue.name }}</span>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="probeStatusClass(readiness.probes.queue.status)">
            {{ readiness.probes.queue.status }}
          </span>
        </div>
        <p class="text-xs text-slate-600 font-medium">{{ readiness.probes.queue.details }}</p>
      </div>

      <!-- Scheduler Probe -->
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
        <div class="flex items-center justify-between gap-2 mb-1">
          <span class="font-bold text-xs text-slate-900">{{ readiness.probes.scheduler.name }}</span>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="probeStatusClass(readiness.probes.scheduler.status)">
            {{ readiness.probes.scheduler.status }}
          </span>
        </div>
        <p class="text-xs text-slate-600 font-medium">{{ readiness.probes.scheduler.details }}</p>
      </div>
    </div>

    <!-- Mandatory Truthful Backup Verification Panel -->
    <div class="mt-4 p-4 bg-amber-50/80 border border-amber-200 rounded-xl">
      <div class="flex items-center justify-between gap-2 mb-2">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span class="font-bold text-xs text-amber-900">{{ readiness.probes.backup_verification.name }}</span>
        </div>
        <span class="px-2.5 py-0.5 bg-amber-200 text-amber-900 font-extrabold rounded text-[10px] uppercase tracking-wider">
          {{ readiness.probes.backup_verification.status }}
        </span>
      </div>

      <p class="text-xs text-amber-800 font-medium mb-2">
        {{ readiness.probes.backup_verification.details }}
      </p>
      <p v-if="readiness.probes.backup_verification.recommendation" class="text-[11px] text-amber-700 italic">
        Guidance: {{ readiness.probes.backup_verification.recommendation }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ContinuityReadiness } from '@/services/governanceService';

const props = defineProps<{
  readiness: ContinuityReadiness;
}>();

const overallBadgeClass = computed(() => {
  switch (props.readiness.overall_status) {
    case 'HEALTHY':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    case 'HEALTHY_WITH_UNVERIFIED_SIGNALS':
      return 'bg-blue-100 text-blue-800 border border-blue-200';
    case 'DEGRADED':
      return 'bg-amber-100 text-amber-800 border border-amber-200';
    case 'FAILED':
      return 'bg-rose-100 text-rose-800 border border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800';
  }
});

function probeStatusClass(status: string): string {
  switch (status) {
    case 'HEALTHY':
      return 'bg-emerald-100 text-emerald-800';
    case 'DEGRADED':
      return 'bg-amber-100 text-amber-800';
    case 'FAILED':
      return 'bg-rose-100 text-rose-800';
    case 'NOT_VERIFIED':
    case 'UNKNOWN':
      return 'bg-amber-200 text-amber-900';
    default:
      return 'bg-slate-100 text-slate-800';
  }
}
</script>
