<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { platformOperationsService, type PlatformAuditLogItem } from '@/services/platformOperationsService';

const loading = ref(false);
const error = ref<string | null>(null);

const auditLogs = ref<PlatformAuditLogItem[]>([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 20,
});

const filters = ref({
  application: '',
  severity: '',
  action: '',
  search: '',
  user_id: '',
  page: 1,
});

const expandedRows = ref<Record<number, boolean>>({});

const toggleRow = (id: number) => {
  expandedRows.value[id] = !expandedRows.value[id];
};

const fetchAuditLogs = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await platformOperationsService.getAuditLogs({
      application: filters.value.application || undefined,
      severity: filters.value.severity || undefined,
      action: filters.value.action || undefined,
      search: filters.value.search || undefined,
      user_id: filters.value.user_id ? Number(filters.value.user_id) : undefined,
      page: filters.value.page,
      per_page: 20,
    });
    auditLogs.value = res.data;
    pagination.value = {
      current_page: res.current_page,
      last_page: res.last_page,
      total: res.total,
      per_page: res.per_page,
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load audit logs.';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  filters.value.page = 1;
  fetchAuditLogs();
};

const resetFilters = () => {
  filters.value = {
    application: '',
    severity: '',
    action: '',
    search: '',
    user_id: '',
    page: 1,
  };
  fetchAuditLogs();
};

const changePage = (newPage: number) => {
  if (newPage >= 1 && newPage <= pagination.value.last_page) {
    filters.value.page = newPage;
    fetchAuditLogs();
  }
};

const formatJson = (val: any) => {
  if (!val) return '—';
  if (typeof val === 'string') return val;
  return JSON.stringify(val, null, 2);
};

const getSeverityClass = (sev: string | null) => {
  switch (sev?.toLowerCase()) {
    case 'critical':
    case 'error':
      return 'bg-red-100 text-red-800 border-red-200';
    case 'warning':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'info':
    default:
      return 'bg-emerald-50 text-emerald-800 border-emerald-200';
  }
};

onMounted(() => {
  fetchAuditLogs();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Unified Audit Search</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Cross-system audit trail spanning all 9 SFU MSA applications and security controls.
        </p>
      </div>
      <button
        @click="fetchAuditLogs"
        :disabled="loading"
        class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-60 cursor-pointer self-start sm:self-auto"
      >
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <!-- Filters Section -->
    <div class="bg-white border border-neutral-ivory rounded-xl p-5 shadow-soft space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Application -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            Application
          </label>
          <select
            v-model="filters.application"
            @change="applyFilters"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Applications</option>
            <option value="platform">Platform Operations</option>
            <option value="ems">EMS (Events)</option>
            <option value="main-website">Main Website</option>
            <option value="cms">CMS</option>
            <option value="dawah-academy">Dawah Academy</option>
            <option value="dams">DAMS</option>
            <option value="store">Store</option>
            <option value="donations">Donations (DMS)</option>
            <option value="sponsorship">Sponsorship (SPMS)</option>
            <option value="mlibms">Library (MLibMS)</option>
          </select>
        </div>

        <!-- Severity -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            Severity
          </label>
          <select
            v-model="filters.severity"
            @change="applyFilters"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Severities</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
            <option value="critical">Critical</option>
          </select>
        </div>

        <!-- Action -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            Action
          </label>
          <input
            v-model="filters.action"
            @keyup.enter="applyFilters"
            placeholder="e.g. login, flush_failed"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <!-- Search Keyword -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            Keyword Search
          </label>
          <input
            v-model="filters.search"
            @keyup.enter="applyFilters"
            placeholder="Search entity, IP, payload"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <!-- User ID -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            User ID
          </label>
          <input
            v-model="filters.user_id"
            type="number"
            @keyup.enter="applyFilters"
            placeholder="User ID"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2 border-t border-neutral-ivory/60">
        <button
          @click="resetFilters"
          class="px-4 py-1.5 text-xs font-bold text-neutral-muted hover:text-neutral-black bg-neutral-background rounded-lg transition cursor-pointer"
        >
          Reset Filters
        </button>
        <button
          @click="applyFilters"
          class="px-4 py-1.5 text-xs font-bold text-white bg-primary hover:bg-primary-hover rounded-lg transition shadow-soft cursor-pointer"
        >
          Apply Filters
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white border border-neutral-ivory rounded-xl shadow-soft overflow-hidden">
      <div v-if="loading && auditLogs.length === 0" class="flex justify-center py-16">
        <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
      </div>

      <div v-else-if="auditLogs.length === 0" class="p-8 text-center text-neutral-muted">
        No audit log records match the selected filters.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="bg-neutral-background/60 border-b border-neutral-ivory text-xs font-bold uppercase tracking-wider text-neutral-muted">
              <th class="py-3 px-4">Time</th>
              <th class="py-3 px-4">Application</th>
              <th class="py-3 px-4">Severity</th>
              <th class="py-3 px-4">Action</th>
              <th class="py-3 px-4">User</th>
              <th class="py-3 px-4">IP / Details</th>
              <th class="py-3 px-4 text-right">Payload</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/60">
            <template v-for="log in auditLogs" :key="log.id">
              <tr class="hover:bg-neutral-background/30 transition">
                <td class="py-3 px-4 font-mono text-xs text-neutral-muted whitespace-nowrap">
                  {{ new Date(log.created_at).toLocaleString() }}
                </td>
                <td class="py-3 px-4">
                  <span class="inline-block px-2.5 py-0.5 text-xs font-mono font-bold rounded-md bg-neutral-background border border-neutral-ivory text-neutral-black">
                    {{ log.application || 'global' }}
                  </span>
                </td>
                <td class="py-3 px-4">
                  <span
                    :class="getSeverityClass(log.severity)"
                    class="inline-block px-2.5 py-0.5 text-xs font-bold uppercase rounded-full border"
                  >
                    {{ log.severity || 'info' }}
                  </span>
                </td>
                <td class="py-3 px-4 font-semibold text-neutral-black">
                  {{ log.action }}
                  <span v-if="log.entity_type" class="block text-xs font-normal text-neutral-muted font-mono">
                    {{ log.entity_type }} #{{ log.entity_id || 'N/A' }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs">
                  <div v-if="log.user" class="font-medium text-neutral-black">
                    {{ log.user.name || (log.user.first_name ? log.user.first_name + ' ' + (log.user.last_name || '') : 'User #' + log.user.id) }}
                    <span class="text-neutral-muted block">#{{ log.user.id }} ({{ log.user.email }})</span>
                  </div>
                  <span v-else class="text-neutral-muted italic">System / Guest</span>
                </td>
                <td class="py-3 px-4 text-xs font-mono text-neutral-muted">
                  {{ log.ip_address || '—' }}
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    @click="toggleRow(log.id)"
                    class="px-2.5 py-1 text-xs font-semibold rounded border border-neutral-ivory bg-white hover:bg-neutral-background transition cursor-pointer"
                  >
                    {{ expandedRows[log.id] ? 'Hide Payload' : 'View Payload' }}
                  </button>
                </td>
              </tr>
              <!-- Expanded Payload Drawer -->
              <tr v-if="expandedRows[log.id]" class="bg-neutral-background/40 border-b border-neutral-ivory">
                <td colspan="7" class="p-4 space-y-3">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Old Values</h4>
                      <pre class="p-3 bg-neutral-black text-emerald-400 font-mono text-xs rounded-lg overflow-x-auto max-h-48">{{ formatJson(log.old_values) }}</pre>
                    </div>
                    <div>
                      <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">New Values</h4>
                      <pre class="p-3 bg-neutral-black text-emerald-400 font-mono text-xs rounded-lg overflow-x-auto max-h-48">{{ formatJson(log.new_values) }}</pre>
                    </div>
                  </div>
                  <div v-if="log.user_agent" class="text-xs font-mono text-neutral-muted">
                    <span class="font-bold">User Agent:</span> {{ log.user_agent }}
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="pagination.total > 0" class="p-4 bg-white border-t border-neutral-ivory flex items-center justify-between">
        <span class="text-xs text-neutral-muted font-medium">
          Showing Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)
        </span>
        <div class="flex gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="px-3 py-1 text-xs font-semibold rounded border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-50 cursor-pointer"
          >
            Previous
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="px-3 py-1 text-xs font-semibold rounded border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-50 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
