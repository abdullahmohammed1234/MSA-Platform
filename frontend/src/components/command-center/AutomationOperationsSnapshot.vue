<script setup lang="ts">
import type { AutomationSnapshot } from '@/services/commandCenterService';

const props = defineProps<{
  automation: AutomationSnapshot;
}>();
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-neutral-black">
          Automation Governance & Remediation Snapshot
        </h3>
        <p class="text-xs text-neutral-muted">
          Aggregated remediation execution performance, resolution effectiveness, and per-action governance telemetry.
        </p>
      </div>

      <span class="text-xs font-mono font-bold text-neutral-muted">
        {{ automation.total_executions ?? 0 }} Total Execution(s)
      </span>
    </div>

    <!-- Telemetry Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <span class="text-xs font-bold text-neutral-muted uppercase">Execution Success</span>
        <div class="mt-1 text-2xl font-extrabold text-emerald-700">
          {{ automation.execution_success_rate ?? 100 }}%
        </div>
        <span class="text-[10px] text-neutral-muted">Completed cleanly</span>
      </div>

      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <span class="text-xs font-bold text-neutral-muted uppercase">Resolution Effectiveness</span>
        <div class="mt-1 text-2xl font-extrabold text-primary">
          {{ automation.problem_resolution_effectiveness_rate ?? 100 }}%
        </div>
        <span class="text-[10px] text-neutral-muted">Alert resolved post-action</span>
      </div>

      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <span class="text-xs font-bold text-neutral-muted uppercase">Pending Approvals</span>
        <div class="mt-1 text-2xl font-extrabold text-amber-600">
          {{ automation.pending_approvals_count ?? 0 }}
        </div>
        <span class="text-[10px] text-neutral-muted">Governance queue</span>
      </div>

      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <span class="text-xs font-bold text-neutral-muted uppercase">Blocked Executions</span>
        <div class="mt-1 text-2xl font-extrabold text-red-600">
          {{ automation.blocked_executions_count ?? 0 }}
        </div>
        <span class="text-[10px] text-neutral-muted">Governance / Threshold</span>
      </div>
    </div>

    <!-- Action Breakdown Table -->
    <div v-if="automation.action_breakdown && automation.action_breakdown.length > 0">
      <h4 class="text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
        Per-Action Governance Breakdown
      </h4>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-neutral-background uppercase text-[10px] font-bold text-neutral-muted tracking-wider border-y border-neutral-ivory">
            <tr>
              <th class="py-2.5 px-3">Remediation Action</th>
              <th class="py-2.5 px-3">Risk Level</th>
              <th class="py-2.5 px-3">Total Executions</th>
              <th class="py-2.5 px-3">Success Rate</th>
              <th class="py-2.5 px-3">Resolution Rate</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr
              v-for="act in automation.action_breakdown"
              :key="act.action_key"
              class="hover:bg-neutral-background/40 transition-colors"
            >
              <td class="py-2.5 px-3 font-semibold text-neutral-black">
                {{ act.name }}
                <span class="text-[10px] text-neutral-muted font-mono block">{{ act.action_key }}</span>
              </td>
              <td class="py-2.5 px-3">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border border-neutral-200 bg-neutral-50 text-neutral-700">
                  {{ act.risk_level }}
                </span>
              </td>
              <td class="py-2.5 px-3 font-mono font-bold">
                {{ act.total_executions }}
              </td>
              <td class="py-2.5 px-3 font-bold text-emerald-700">
                {{ act.success_rate }}%
              </td>
              <td class="py-2.5 px-3 font-bold text-primary">
                {{ act.resolution_effectiveness_rate }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
