<script setup lang="ts">
import { computed } from 'vue';
import type { PlatformStatus } from '@/services/commandCenterService';

const props = defineProps<{
  status: PlatformStatus;
}>();

const statusBadgeClass = computed(() => {
  switch (props.status.overall_status) {
    case 'critical':
      return 'bg-red-500 text-white';
    case 'degraded':
      return 'bg-amber-500 text-white';
    case 'attention_required':
      return 'bg-yellow-500 text-neutral-black';
    default:
      return 'bg-emerald-600 text-white';
  }
});
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
    <!-- Overall Status -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm flex flex-col justify-between">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        Platform Health
      </span>
      <div class="mt-2">
        <span :class="['px-2.5 py-1 text-xs font-bold uppercase rounded-lg inline-block', statusBadgeClass]">
          {{ status.overall_status.replace('_', ' ') }}
        </span>
      </div>
    </div>

    <!-- Critical Alerts -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        Critical Alerts
      </span>
      <div class="mt-2 flex items-baseline justify-between">
        <span class="text-2xl font-extrabold text-red-600">
          {{ status.critical_alerts_count }}
        </span>
        <span class="text-[10px] text-neutral-muted">Active</span>
      </div>
    </div>

    <!-- High Alerts -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        High Alerts
      </span>
      <div class="mt-2 flex items-baseline justify-between">
        <span class="text-2xl font-extrabold text-amber-600">
          {{ status.high_alerts_count }}
        </span>
        <span class="text-[10px] text-neutral-muted">Active</span>
      </div>
    </div>

    <!-- Pending Approvals -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        Pending Approvals
      </span>
      <div class="mt-2 flex items-baseline justify-between">
        <span class="text-2xl font-extrabold text-primary">
          {{ status.pending_approvals_count }}
        </span>
        <span class="text-[10px] text-neutral-muted">Governance</span>
      </div>
    </div>

    <!-- Blocked Automations -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        Blocked Remediation
      </span>
      <div class="mt-2 flex items-baseline justify-between">
        <span class="text-2xl font-extrabold text-orange-600">
          {{ status.blocked_automations_count }}
        </span>
        <span class="text-[10px] text-neutral-muted">Actions</span>
      </div>
    </div>

    <!-- Failed Queue Jobs -->
    <div class="bg-white rounded-xl border border-neutral-ivory p-4 shadow-sm">
      <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider">
        Failed Queue Jobs
      </span>
      <div class="mt-2 flex items-baseline justify-between">
        <span class="text-2xl font-extrabold text-neutral-black">
          {{ status.failed_jobs_count }}
        </span>
        <span class="text-[10px] text-neutral-muted">Queues</span>
      </div>
    </div>
  </div>
</template>
