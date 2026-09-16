<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 overflow-hidden">
    <div class="flex items-center justify-between mb-4 gap-3">
      <div>
        <h3 class="text-base font-bold text-slate-900">Data Integrity Diagnostics</h3>
        <p class="text-xs text-slate-500">Deterministic check findings for EMS, Donations, Store, MLibMS, and Operations</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <span
          class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider shrink-0"
          :class="overallBadgeClass"
        >
          {{ result.status }}
        </span>
      </div>
    </div>

    <!-- Diagnostic Checks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
      <div
        v-for="check in result.checks"
        :key="check.check_key"
        class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex flex-col justify-between overflow-hidden shadow-2xs transition-all hover:bg-slate-100/60"
      >
        <div>
          <!-- Header row: Domain badge, check_key, and status badge -->
          <div class="flex items-start justify-between gap-2 mb-2 min-w-0">
            <div class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
              <span class="px-2 py-0.5 bg-slate-200 text-slate-700 font-bold rounded text-[10px] uppercase shrink-0">
                {{ check.domain }}
              </span>
              <span class="font-mono text-xs font-bold text-slate-800 break-all leading-snug truncate sm:whitespace-normal">
                {{ check.check_key }}
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase shrink-0 self-start tracking-wide"
              :class="statusBadgeClass(check.status)"
            >
              {{ check.status }}
            </span>
          </div>

          <!-- Diagnostic summary with wrap and overflow protection -->
          <p class="text-xs text-slate-600 font-medium mt-1.5 break-words [overflow-wrap:anywhere] leading-relaxed">
            {{ check.summary }}
          </p>
        </div>

        <div v-if="check.issue_count > 0" class="mt-2.5 text-[11px] font-bold text-rose-600 flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
          <span>Issues detected: {{ check.issue_count }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { IntegrityScanResult } from '@/services/governanceService';

const props = defineProps<{
  result: IntegrityScanResult;
}>();

const overallBadgeClass = computed(() => {
  switch (props.result.status) {
    case 'PASSED':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    case 'WARNING':
      return 'bg-amber-100 text-amber-800 border border-amber-200';
    case 'FAILED':
      return 'bg-rose-100 text-rose-800 border border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800';
  }
});

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'PASSED':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    case 'WARNING':
      return 'bg-amber-100 text-amber-800 border border-amber-200';
    case 'FAILED':
      return 'bg-rose-100 text-rose-800 border border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800';
  }
}
</script>
