<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { connectAdminService } from '@/services/admin/connectAdminService';
import type { ConnectAuditItem } from '@/services/admin/connectAdminService';
import {
  History,
  User,
  Clock,
  Globe,
  ChevronDown,
  ChevronUp,
} from 'lucide-vue-next';

const isLoading = ref(true);
const error = ref<string | null>(null);
const logs = ref<ConnectAuditItem[]>([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const expandedRows = ref<Set<number>>(new Set());

const fetchLogs = async (page = 1) => {
  try {
    isLoading.value = true;
    error.value = null;
    const res = await connectAdminService.getAuditLogs({ page, per_page: 20 });
    logs.value = res.logs.data;
    total.value = res.logs.total;
    currentPage.value = res.logs.current_page;
    lastPage.value = res.logs.last_page;
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load audit logs.';
  } finally {
    isLoading.value = false;
  }
};

const toggleRow = (id: number) => {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id);
  } else {
    expandedRows.value.add(id);
  }
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return dateStr;
  }
};

onMounted(() => {
  void fetchLogs(1);
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-xl font-bold text-neutral-900">MSA Connect System Audit Trail</h1>
      <p class="text-xs text-neutral-500 mt-1">
        Immutable record of administrative configuration updates, operational actions, and integration changes.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-2xl border border-[#ebe8de] p-8 text-center text-xs text-neutral-400">
      Loading audit history...
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700">
      {{ error }}
    </div>

    <!-- Empty State -->
    <div v-else-if="logs.length === 0" class="bg-white rounded-2xl border border-[#ebe8de] p-12 text-center space-y-2">
      <History class="w-8 h-8 text-neutral-300 mx-auto" />
      <div class="text-sm font-bold text-neutral-700">No Audit Events Recorded</div>
      <p class="text-xs text-neutral-500 max-w-sm mx-auto">
        Administrative actions and configuration updates will be automatically logged and viewable here.
      </p>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#faf8f5] border-b border-[#ebe8de] text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">
            <tr>
              <th class="py-3 px-4">Timestamp</th>
              <th class="py-3 px-4">Action</th>
              <th class="py-3 px-4">Severity</th>
              <th class="py-3 px-4">Administrator</th>
              <th class="py-3 px-4">IP Address</th>
              <th class="py-3 px-4 text-right">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#ebe8de]">
            <template v-for="log in logs" :key="log.id">
              <tr class="hover:bg-[#faf8f5]/60 transition-colors cursor-pointer" @click="toggleRow(log.id)">
                <td class="py-3.5 px-4 text-neutral-600 whitespace-nowrap">
                  <div class="flex items-center gap-1.5 font-mono text-[11px]">
                    <Clock class="w-3.5 h-3.5 text-neutral-400" />
                    <span>{{ formatDate(log.created_at) }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 font-bold text-neutral-900">
                  <span class="font-mono text-xs">{{ log.action }}</span>
                  <div v-if="log.description" class="text-[11px] text-neutral-500 font-normal mt-0.5">
                    {{ log.description }}
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                    :class="log.severity === 'critical' ? 'bg-red-50 text-red-700' : (log.severity === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-neutral-100 text-neutral-700')"
                  >
                    {{ log.severity }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-neutral-600">
                  <div class="flex items-center gap-1.5">
                    <User class="w-3.5 h-3.5 text-neutral-400" />
                    <span>{{ log.user?.name || 'System' }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-neutral-500 font-mono text-[11px]">
                  <div class="flex items-center gap-1">
                    <Globe class="w-3 h-3 text-neutral-400" />
                    <span>{{ log.ip_address || '—' }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <button type="button" class="text-neutral-400 hover:text-neutral-800 p-1">
                    <component :is="expandedRows.has(log.id) ? ChevronUp : ChevronDown" class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="expandedRows.has(log.id)" class="bg-[#faf8f5]/80">
                <td colspan="6" class="p-4 border-t border-[#ebe8de]">
                  <div class="space-y-1.5">
                    <div class="text-[11px] font-bold text-neutral-700 uppercase tracking-wider">Payload Details</div>
                    <pre class="bg-neutral-900 text-neutral-100 p-3 rounded-xl text-[11px] font-mono overflow-x-auto">{{ JSON.stringify(log.payload, null, 2) || '{}' }}</pre>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="lastPage > 1" class="p-4 border-t border-[#ebe8de] flex items-center justify-between text-xs text-neutral-500">
        <div>Showing page {{ currentPage }} of {{ lastPage }} ({{ total }} total logs)</div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="fetchLogs(currentPage - 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Previous
          </button>
          <button
            type="button"
            :disabled="currentPage >= lastPage"
            @click="fetchLogs(currentPage + 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
