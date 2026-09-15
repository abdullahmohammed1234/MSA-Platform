<script setup lang="ts">
import type { InfrastructureHealth } from '@/services/commandCenterService';

const props = defineProps<{
  infra: InfrastructureHealth;
}>();

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'operational':
    case 'healthy':
      return { text: 'Operational', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    case 'degraded':
      return { text: 'Degraded', class: 'bg-amber-100 text-amber-800 border-amber-200' };
    case 'unavailable':
      return { text: 'Unavailable', class: 'bg-red-100 text-red-800 border-red-200' };
    default:
      return { text: 'Unknown', class: 'bg-neutral-100 text-neutral-700 border-neutral-200' };
  }
};
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <h3 class="text-base font-bold text-neutral-black mb-1">
      Platform Infrastructure Health
    </h3>
    <p class="text-xs text-neutral-muted mb-4">
      Live probe signals for database connectivity, queue workers, storage, scheduler, and mail subsystem.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Database -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Database Engine</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.database?.status).class]">
            {{ getStatusBadge(infra.database?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.database?.message || 'DB probe active' }}
        </p>
        <span class="text-[10px] font-mono text-neutral-muted mt-2 block">
          Driver: {{ infra.database?.driver || 'mysql' }}
        </span>
      </div>

      <!-- Queues -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Queue Workers</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.queues?.status).class]">
            {{ getStatusBadge(infra.queues?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.queues?.message || 'Queues active' }}
        </p>
        <span class="text-[10px] font-mono text-neutral-muted mt-2 block">
          Failed Jobs: {{ infra.queues?.failed_jobs ?? 0 }}
        </span>
      </div>

      <!-- Storage -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Storage Subsystem</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.storage?.status).class]">
            {{ getStatusBadge(infra.storage?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.storage?.message || 'Public storage disk reachable' }}
        </p>
      </div>

      <!-- Scheduler -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Background Scheduler</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.scheduler?.status).class]">
            {{ getStatusBadge(infra.scheduler?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.scheduler?.message || 'Scheduler operational' }}
        </p>
      </div>

      <!-- Communications -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Communications</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.communications?.status).class]">
            {{ getStatusBadge(infra.communications?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.communications?.message || 'Email delivery active' }}
        </p>
      </div>

      <!-- Email Subsystem -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-neutral-background/50">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Mail Gateway</span>
          <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border', getStatusBadge(infra.email?.status).class]">
            {{ getStatusBadge(infra.email?.status).text }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">
          {{ infra.email?.message || 'Mail gateway responsive' }}
        </p>
      </div>
    </div>
  </div>
</template>
