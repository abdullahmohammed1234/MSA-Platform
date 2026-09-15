<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { operationsService, type AutomationHealthSummary } from '@/services/operationsService';

const loading = ref(true);
const metrics = ref<AutomationHealthSummary | null>(null);
const errorMessage = ref<string | null>(null);

const loadHealthMetrics = async () => {
  loading.value = true;
  errorMessage.value = null;
  try {
    const res = await operationsService.getGovernanceHealth();
    metrics.value = res.metrics;
  } catch (err: any) {
    console.error('Failed to load automation health metrics:', err);
    errorMessage.value = 'Failed to load automation health metrics.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadHealthMetrics();
});

const riskBadgeClass = (risk: string) => {
  switch (risk) {
    case 'critical':
      return 'bg-red-100 text-red-800 border-red-200';
    case 'high':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'medium':
      return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    default:
      return 'bg-blue-100 text-blue-800 border-blue-200';
  }
};

defineExpose({
  loadHealthMetrics,
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
          Automation Intelligence & Governance Health
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">
          Deterministic effectiveness monitoring distinguishing execution success from problem resolution.
        </p>
      </div>

      <button
        @click="loadHealthMetrics"
        :disabled="loading"
        class="px-3 py-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer inline-flex items-center gap-1.5"
      >
        <svg :class="['h-3.5 w-3.5', loading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh Metrics
      </button>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="p-4 bg-red-50 text-red-900 border border-red-200 rounded-xl text-xs">
      {{ errorMessage }}
    </div>

    <!-- KPI Summary Cards -->
    <div v-if="metrics" class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Total Executions</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-2xl font-extrabold text-gray-900">{{ metrics.total_executions }}</span>
          <span class="text-[10px] text-gray-400 font-mono">Attempts</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Execution Success Rate</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-2xl font-extrabold text-emerald-600">{{ metrics.execution_success_rate }}%</span>
          <span class="text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-mono">Code Ran</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Problem Resolution Rate</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-2xl font-extrabold text-teal-600">{{ metrics.problem_resolution_effectiveness_rate }}%</span>
          <span class="text-[10px] text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded font-mono">Alert Cleared</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Governance Blocked</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-2xl font-extrabold text-amber-600">{{ metrics.blocked_executions_count }}</span>
          <span class="text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded font-mono">Cooldown / Block</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Pending Approvals</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-2xl font-extrabold text-blue-600">{{ metrics.pending_approvals_count }}</span>
          <span class="text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-mono">Queue</span>
        </div>
      </div>
    </div>

    <!-- Action Effectiveness Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-3 p-4">
      <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">
        Remediation Effectiveness Breakdown by Action Handler
      </h3>

      <div v-if="loading" class="p-8 text-center text-gray-400 text-xs">
        Evaluating automation metrics...
      </div>

      <div v-else-if="!metrics || !metrics.action_breakdown || metrics.action_breakdown.length === 0" class="p-8 text-center text-gray-500 text-xs">
        No registered action execution metrics recorded yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50/80 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wider text-gray-400">
              <th class="py-2.5 px-3">Risk / Action Key</th>
              <th class="py-2.5 px-3">Action Name</th>
              <th class="py-2.5 px-3 text-right">Executions</th>
              <th class="py-2.5 px-3 text-right">Success</th>
              <th class="py-2.5 px-3 text-right">Resolved</th>
              <th class="py-2.5 px-3 text-right">Execution Success Rate</th>
              <th class="py-2.5 px-3 text-right">Resolution Effectiveness</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in metrics.action_breakdown" :key="item.action_key" class="hover:bg-gray-50/60">
              <td class="py-3 px-3 align-top whitespace-nowrap">
                <span :class="['px-2 py-0.5 text-[9px] font-bold uppercase rounded border inline-block mr-2', riskBadgeClass(item.risk_level)]">
                  {{ item.risk_level }}
                </span>
                <span class="font-mono text-gray-700 text-[11px] font-semibold">{{ item.action_key }}</span>
              </td>

              <td class="py-3 px-3 align-top text-gray-900 font-medium">
                {{ item.name }}
              </td>

              <td class="py-3 px-3 align-top text-right font-mono text-gray-700">
                {{ item.total_executions }}
              </td>

              <td class="py-3 px-3 align-top text-right font-mono text-emerald-700 font-semibold">
                {{ item.success_count }}
              </td>

              <td class="py-3 px-3 align-top text-right font-mono text-teal-700 font-semibold">
                {{ item.resolved_count }}
              </td>

              <td class="py-3 px-3 align-top text-right font-mono">
                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded', item.success_rate >= 90 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800']">
                  {{ item.success_rate }}%
                </span>
              </td>

              <td class="py-3 px-3 align-top text-right font-mono">
                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded', item.resolution_effectiveness_rate >= 80 ? 'bg-teal-50 text-teal-800' : 'bg-yellow-50 text-yellow-800']">
                  {{ item.resolution_effectiveness_rate }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
