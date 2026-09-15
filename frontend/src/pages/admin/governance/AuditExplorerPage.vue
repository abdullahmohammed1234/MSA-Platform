<template>
  <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-slate-900">Audit Explorer</h1>
            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full border border-slate-200">
              Authoritative Audit Logs
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-1">
            Search, filter, and inspect administrative audit activity across all platform domains.
          </p>
        </div>

        <router-link
          to="/admin/governance"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition flex items-center gap-1.5 self-start md:self-auto"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Governance Dashboard
        </router-link>
      </div>

      <!-- Filters Bar -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Search</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search action, description, IP..."
            class="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            @keyup.enter="applyFilters"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Domain / Application</label>
          <select
            v-model="filters.application"
            class="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
            @change="applyFilters"
          >
            <option value="">All Applications</option>
            <option value="ems">EMS</option>
            <option value="donations">Donations</option>
            <option value="store">Store</option>
            <option value="mlibms">MLibMS</option>
            <option value="operations">Operations</option>
            <option value="security">Security</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Severity</label>
          <select
            v-model="filters.severity"
            class="w-full px-3 py-1.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
            @change="applyFilters"
          >
            <option value="">All Severities</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="critical">Critical</option>
          </select>
        </div>

        <div class="flex items-end">
          <button
            @click="applyFilters"
            class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition"
          >
            Apply Filters
          </button>
        </div>
      </div>

      <!-- Loading / Error -->
      <div v-if="loading" class="py-16 text-center text-slate-500 text-sm">
        Fetching audit records...
      </div>

      <div v-else-if="error" class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-sm">
        {{ error }}
      </div>

      <!-- Audit Logs Table -->
      <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-bold text-[10px]">
                <th class="py-3 px-4">ID</th>
                <th class="py-3 px-4">Timestamp</th>
                <th class="py-3 px-4">Actor</th>
                <th class="py-3 px-4">Domain</th>
                <th class="py-3 px-4">Action</th>
                <th class="py-3 px-4">Severity</th>
                <th class="py-3 px-4">Description</th>
                <th class="py-3 px-4 text-right">Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="logs.length === 0">
                <td colspan="8" class="py-8 text-center text-slate-400">
                  No matching audit logs found.
                </td>
              </tr>
              <tr
                v-for="log in logs"
                :key="log.id"
                class="hover:bg-slate-50 transition"
              >
                <td class="py-3 px-4 font-mono font-medium text-slate-500">#{{ log.id }}</td>
                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                <td class="py-3 px-4 font-semibold text-slate-800">{{ log.user?.name || 'System' }}</td>
                <td class="py-3 px-4">
                  <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-semibold rounded text-[10px] uppercase">
                    {{ log.application || 'System' }}
                  </span>
                </td>
                <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ log.action }}</td>
                <td class="py-3 px-4">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="severityClass(log.severity)">
                    {{ log.severity }}
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-700 max-w-xs truncate">{{ log.description || log.action }}</td>
                <td class="py-3 px-4 text-right">
                  <button
                    @click="selectedLog = log"
                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded text-[10px]"
                  >
                    Inspect
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div v-if="pagination.total > 0" class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs">
          <span class="text-slate-500 font-medium">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} records
          </span>

          <div class="flex items-center gap-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1 bg-white border border-slate-300 rounded font-semibold text-slate-700 disabled:opacity-50"
            >
              Previous
            </button>
            <span class="font-bold text-slate-800">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1 bg-white border border-slate-300 rounded font-semibold text-slate-700 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div
      v-if="selectedLog"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Audit Record #{{ selectedLog.id }}</h3>
          <button @click="selectedLog = null" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100">
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">Action</span><span class="font-mono font-bold">{{ selectedLog.action }}</span></div>
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">Actor</span><span>{{ selectedLog.user?.name || 'System' }} ({{ selectedLog.user?.email || 'N/A' }})</span></div>
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">Domain</span><span>{{ selectedLog.application || 'System' }}</span></div>
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">Timestamp</span><span>{{ selectedLog.created_at }}</span></div>
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">IP Address</span><span>{{ selectedLog.ip_address || 'N/A' }}</span></div>
          <div><span class="font-bold text-slate-500 uppercase text-[9px] block">Target</span><span>{{ selectedLog.target_type ? `${selectedLog.target_type} #${selectedLog.target_id}` : 'N/A' }}</span></div>
        </div>

        <div>
          <span class="font-bold text-slate-700 text-xs block mb-1">Payload (Sanitized)</span>
          <pre class="bg-slate-900 text-slate-100 p-3 rounded-lg text-[11px] overflow-x-auto font-mono max-h-60">{{ JSON.stringify(selectedLog.payload, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue';
import { governanceService, type AuditLogItem } from '@/services/governanceService';

const loading = ref(true);
const error = ref<string | null>(null);
const logs = ref<AuditLogItem[]>([]);
const selectedLog = ref<AuditLogItem | null>(null);

const filters = reactive({
  search: '',
  application: '',
  severity: '',
  page: 1,
});

const pagination = reactive({
  total: 0,
  current_page: 1,
  last_page: 1,
  per_page: 20,
});

async function fetchLogs() {
  loading.value = true;
  error.value = null;

  try {
    const res = await governanceService.searchAuditLogs({
      search: filters.search,
      application: filters.application,
      severity: filters.severity,
      page: filters.page,
    });

    logs.value = res.data;
    pagination.total = res.total;
    pagination.current_page = res.current_page;
    pagination.last_page = res.last_page;
    pagination.per_page = res.per_page;
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load audit logs.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  filters.page = 1;
  fetchLogs();
}

function changePage(newPage: number) {
  if (newPage < 1 || newPage > pagination.last_page) return;
  filters.page = newPage;
  fetchLogs();
}

function severityClass(severity: string): string {
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

onMounted(() => {
  fetchLogs();
});
</script>
