<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-slate-900">Audit Activity</h3>
        <p class="text-xs text-slate-500">Authoritative administrative audit records</p>
      </div>
      <router-link
        to="/admin/governance/audit"
        class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline flex items-center gap-1"
      >
        View All Audit Logs
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>

    <div v-if="logs.length === 0" class="text-center py-8 text-slate-400 text-xs">
      No recent audit log records.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="log in logs"
        :key="log.id"
        class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span
              class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
              :class="severityBadgeClass(log.severity)"
            >
              {{ log.severity }}
            </span>
            <span class="font-mono font-semibold text-slate-800">{{ log.action }}</span>
            <span v-if="log.application" class="px-1.5 py-0.5 bg-slate-200 text-slate-700 rounded text-[10px]">
              {{ log.application }}
            </span>
          </div>
          <p class="text-slate-600 font-medium">{{ log.description || log.action }}</p>
          <div class="text-[11px] text-slate-400">
            By <span class="font-semibold text-slate-600">{{ log.user?.name || 'System / Anonymous' }}</span>
          </div>
        </div>
        <div class="text-slate-400 text-[11px] whitespace-nowrap self-start sm:self-center">
          {{ formatDate(log.created_at) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { AuditLogItem } from '@/services/governanceService';

defineProps<{
  logs: AuditLogItem[];
}>();

function severityBadgeClass(severity: string): string {
  switch (severity) {
    case 'critical':
      return 'bg-rose-100 text-rose-800';
    case 'warning':
      return 'bg-amber-100 text-amber-800';
    default:
      return 'bg-blue-100 text-blue-800';
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}
</script>
