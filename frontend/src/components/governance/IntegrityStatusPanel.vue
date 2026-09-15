<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-slate-900">Data Integrity Diagnostics</h3>
        <p class="text-xs text-slate-500">Deterministic check findings for EMS, Donations, Store, MLibMS, and Operations</p>
      </div>
      <div class="flex items-center gap-2">
        <span
          class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
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
        class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex flex-col justify-between"
      >
        <div class="flex items-start justify-between gap-2 mb-1.5">
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 bg-slate-200 text-slate-700 font-bold rounded text-[10px] uppercase">
              {{ check.domain }}
            </span>
            <span class="font-mono text-xs font-bold text-slate-800">{{ check.check_key }}</span>
          </div>
          <span
            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
            :class="statusBadgeClass(check.status)"
          >
            {{ check.status }}
          </span>
        </div>

        <p class="text-xs text-slate-600 font-medium mt-1">{{ check.summary }}</p>

        <div v-if="check.issue_count > 0" class="mt-2 text-[11px] font-bold text-rose-600">
          Issues detected: {{ check.issue_count }}
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
      return 'bg-emerald-100 text-emerald-800';
    case 'WARNING':
      return 'bg-amber-100 text-amber-800';
    case 'FAILED':
      return 'bg-rose-100 text-rose-800';
    default:
      return 'bg-slate-100 text-slate-800';
  }
}
</script>
