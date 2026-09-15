<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { operationsService, type OperationalAlert, type OperationsSummary } from '@/services/operationsService';
import OperationalAlertModal from '@/components/operations/OperationalAlertModal.vue';

const router = useRouter();

const loading = ref(true);
const detecting = ref(false);
const alerts = ref<OperationalAlert[]>([]);
const summary = ref<OperationsSummary | null>(null);

const currentPage = ref(1);
const totalPages = ref(1);
const totalAlerts = ref(0);

const selectedCategory = ref('');
const selectedSeverity = ref('');
const selectedStatus = ref('open');
const searchQuery = ref('');

const activeAlertModal = ref<OperationalAlert | null>(null);
const isModalOpen = ref(false);
const notificationMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

const loadSummary = async () => {
  try {
    const res = await operationsService.getSummary();
    summary.value = res.summary;
  } catch (err: any) {
    console.error('Failed to fetch operations summary:', err);
  }
};

const loadAlerts = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: 15,
      category: selectedCategory.value || undefined,
      severity: selectedSeverity.value || undefined,
      status: selectedStatus.value || undefined,
      search: searchQuery.value || undefined,
    };
    const res = await operationsService.getAlerts(params);
    alerts.value = res.alerts.data;
    currentPage.value = res.alerts.current_page;
    totalPages.value = res.alerts.last_page;
    totalAlerts.value = res.alerts.total;
  } catch (err: any) {
    showNotification('error', 'Failed to load operational alerts.');
  } finally {
    loading.value = false;
  }
};

const runDetection = async () => {
  detecting.value = true;
  try {
    const res = await operationsService.runDetection();
    showNotification('success', res.message);
    await Promise.all([loadSummary(), loadAlerts(1)]);
  } catch (err: any) {
    showNotification('error', 'Failed to execute operational rule detection.');
  } finally {
    detecting.value = false;
  }
};

const showNotification = (type: 'success' | 'error', text: string) => {
  notificationMessage.value = { type, text };
  setTimeout(() => {
    notificationMessage.value = null;
  }, 4000);
};

const openAlertDetail = (alert: OperationalAlert) => {
  activeAlertModal.value = alert;
  isModalOpen.value = true;
};

const handleAcknowledge = async (alertId: number) => {
  try {
    const res = await operationsService.acknowledgeAlert(alertId);
    showNotification('success', 'Alert acknowledged successfully.');
    if (activeAlertModal.value?.id === alertId) {
      activeAlertModal.value = res.alert;
    }
    await Promise.all([loadSummary(), loadAlerts(currentPage.value)]);
  } catch (err: any) {
    showNotification('error', 'Failed to acknowledge alert.');
  }
};

const handleResolve = async ({ alertId, reason }: { alertId: number; reason: string }) => {
  try {
    await operationsService.resolveAlert(alertId, reason);
    showNotification('success', 'Alert resolved successfully.');
    isModalOpen.value = false;
    await Promise.all([loadSummary(), loadAlerts(currentPage.value)]);
  } catch (err: any) {
    showNotification('error', 'Failed to resolve alert.');
  }
};

const handleDismiss = async ({ alertId, reason }: { alertId: number; reason: string }) => {
  try {
    await operationsService.dismissAlert(alertId, reason);
    showNotification('success', 'Alert dismissed.');
    isModalOpen.value = false;
    await Promise.all([loadSummary(), loadAlerts(currentPage.value)]);
  } catch (err: any) {
    showNotification('error', 'Failed to dismiss alert.');
  }
};

const handleReopen = async (alertId: number) => {
  try {
    const res = await operationsService.reopenAlert(alertId);
    showNotification('success', 'Alert reopened.');
    if (activeAlertModal.value?.id === alertId) {
      activeAlertModal.value = res.alert;
    }
    await Promise.all([loadSummary(), loadAlerts(currentPage.value)]);
  } catch (err: any) {
    showNotification('error', 'Failed to reopen alert.');
  }
};

watch([selectedCategory, selectedSeverity, selectedStatus], () => {
  loadAlerts(1);
});

let searchTimeout: any = null;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadAlerts(1);
  }, 350);
};

onMounted(() => {
  loadSummary();
  loadAlerts(1);
});

const severityBadgeClass = (severity: string) => {
  switch (severity) {
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

const statusBadgeClass = (status: string) => {
  switch (status) {
    case 'open':
      return 'bg-red-50 text-red-700 border-red-300';
    case 'acknowledged':
      return 'bg-amber-50 text-amber-700 border-amber-300';
    case 'resolved':
      return 'bg-emerald-50 text-emerald-700 border-emerald-300';
    case 'dismissed':
      return 'bg-gray-100 text-gray-600 border-gray-300';
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200';
  }
};

const formatDate = (isoStr?: string | null) => {
  if (!isoStr) return 'N/A';
  return new Date(isoStr).toLocaleString();
};
import AutomationHealthDashboard from '@/components/operations/AutomationHealthDashboard.vue';

const healthDashboardRef = ref<any>(null);

const handleRemediationExecuted = async (execution: any) => {
  showNotification('success', `Automated remediation executed: ${execution.summary || execution.action_key}`);
  await Promise.all([loadSummary(), loadAlerts(currentPage.value)]);
  if (healthDashboardRef.value) {
    healthDashboardRef.value.loadHealthMetrics();
  }
};
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs text-gray-400">SFU MSA Platform Operations</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
          Operations Control & Action Center
        </h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Deterministic issue detection, governed remediation action execution, and real-time operational state resolution.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          @click="runDetection"
          :disabled="detecting"
          class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-lg shadow-sm transition-all disabled:opacity-50 cursor-pointer min-h-[44px]"
        >
          <svg v-if="!detecting" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <svg v-else class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ detecting ? 'Scanning Rules...' : 'Run Detection Engine' }}
        </button>

        <button
          @click="router.push('/admin/operations/history')"
          class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer min-h-[44px]"
        >
          <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Audit & Governance Log
        </button>
      </div>
    </div>

    <!-- Toast Notification Banner -->
    <div
      v-if="notificationMessage"
      :class="['p-4 rounded-xl text-xs font-semibold flex items-center justify-between border shadow-sm transition-all', notificationMessage.type === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-200' : 'bg-red-50 text-red-900 border-red-200']"
    >
      <span>{{ notificationMessage.text }}</span>
      <button @click="notificationMessage = null" class="text-gray-400 hover:text-gray-600">✕</button>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" v-if="summary">
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-xs uppercase tracking-wider font-bold text-gray-400">Total Active Alerts</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-red-600">{{ summary.total_active }}</span>
          <span class="text-[10px] text-gray-400 font-mono">Open & Ack</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-xs uppercase tracking-wider font-bold text-gray-400">Critical & High Priority</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-amber-600">{{ (summary.by_severity.critical || 0) + (summary.by_severity.high || 0) }}</span>
          <span class="text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded font-mono">Action Needed</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-xs uppercase tracking-wider font-bold text-gray-400">Resolved Alerts</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-emerald-600">{{ summary.by_status.resolved }}</span>
          <span class="text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-mono">Closed</span>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
        <span class="text-xs uppercase tracking-wider font-bold text-gray-400">Dismissed Alerts</span>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-gray-500">{{ summary.by_status.dismissed }}</span>
          <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded font-mono">Archived</span>
        </div>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Search Input -->
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Search Alerts</label>
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="Title, rule, description..."
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px]"
          />
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Status</label>
          <select
            v-model="selectedStatus"
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px] bg-white"
          >
            <option value="">All Statuses</option>
            <option value="open">Open (Unacknowledged)</option>
            <option value="acknowledged">Acknowledged</option>
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </div>

        <!-- Category Filter -->
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Subsystem Category</label>
          <select
            v-model="selectedCategory"
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px] bg-white"
          >
            <option value="">All Categories</option>
            <option value="ems">EMS (Events)</option>
            <option value="donations">Donations</option>
            <option value="store">Store</option>
            <option value="mlibms">MLibMS (Library)</option>
            <option value="communications">Communications</option>
            <option value="volunteering">Volunteering</option>
            <option value="platform">Platform</option>
          </select>
        </div>

        <!-- Severity Filter -->
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Severity Level</label>
          <select
            v-model="selectedSeverity"
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px] bg-white"
          >
            <option value="">All Severities</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Operational Alerts List Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <!-- Loading state -->
      <div v-if="loading" class="p-12 text-center text-gray-400 text-xs">
        <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-emerald-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Loading operational alerts...
      </div>

      <!-- Empty state -->
      <div v-else-if="alerts.length === 0" class="p-12 text-center text-gray-500 space-y-2">
        <svg class="h-10 w-10 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm font-bold text-gray-900">No operational alerts found</p>
        <p class="text-xs text-gray-400 max-w-sm mx-auto">
          No active or matched operational alerts match your criteria. Click "Run Detection Engine" to re-evaluate operational conditions.
        </p>
      </div>

      <!-- Desktop & Tablet Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50/80 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wider text-gray-400">
              <th class="py-3 px-4">Severity / Category</th>
              <th class="py-3 px-4">Alert & Description</th>
              <th class="py-3 px-4">Rule & Source</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Last Detected</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="alert in alerts"
              :key="alert.id"
              class="hover:bg-gray-50/60 transition-colors"
            >
              <!-- Severity / Category -->
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <div class="flex flex-col gap-1">
                  <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded border inline-block w-max', severityBadgeClass(alert.severity)]">
                    {{ alert.severity }}
                  </span>
                  <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">
                    {{ alert.category }}
                  </span>
                </div>
              </td>

              <!-- Title & Description -->
              <td class="py-3.5 px-4 align-top max-w-md">
                <button
                  @click="openAlertDetail(alert)"
                  class="font-bold text-gray-900 hover:text-emerald-700 text-left transition-colors cursor-pointer block mb-0.5"
                >
                  {{ alert.title }}
                </button>
                <p class="text-gray-500 text-[11px] line-clamp-2 leading-relaxed">
                  {{ alert.description }}
                </p>
              </td>

              <!-- Rule & Source -->
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <div class="font-mono text-gray-700 text-[11px] font-medium">{{ alert.rule_key }}</div>
                <div class="text-[10px] text-gray-400">{{ alert.source_type }} #{{ alert.source_id }}</div>
              </td>

              <!-- Status -->
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <span :class="['px-2 py-0.5 text-[10px] font-bold capitalize rounded border', statusBadgeClass(alert.status)]">
                  {{ alert.status }}
                </span>
              </td>

              <!-- Last Detected -->
              <td class="py-3.5 px-4 align-top whitespace-nowrap text-gray-500 text-[11px]">
                {{ formatDate(alert.last_detected_at) }}
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 align-top text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="openAlertDetail(alert)"
                    class="px-2.5 py-1 text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded transition-colors cursor-pointer"
                  >
                    View / Remediate
                  </button>

                  <button
                    v-if="alert.status === 'open'"
                    @click="handleAcknowledge(alert.id)"
                    class="px-2.5 py-1 text-xs font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 rounded transition-colors cursor-pointer"
                  >
                    Ack
                  </button>

                  <a
                    v-if="alert.action_url"
                    :href="alert.action_url"
                    target="_blank"
                    class="px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded transition-colors inline-flex items-center gap-1"
                  >
                    Manual ↗
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="totalPages > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs">
        <span class="text-gray-500">
          Showing page <span class="font-bold text-gray-900">{{ currentPage }}</span> of <span class="font-bold text-gray-900">{{ totalPages }}</span> ({{ totalAlerts }} alerts total)
        </span>
        <div class="flex items-center gap-2">
          <button
            @click="loadAlerts(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1.5 font-semibold text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40 min-h-[36px]"
          >
            Previous
          </button>
          <button
            @click="loadAlerts(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-1.5 font-semibold text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40 min-h-[36px]"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Phase 26 Automation Intelligence & Governance Health Dashboard -->
    <div class="pt-6 border-t border-gray-200">
      <AutomationHealthDashboard ref="healthDashboardRef" />
    </div>

    <!-- Alert Modal Component -->
    <OperationalAlertModal
      :alert="activeAlertModal"
      :is-open="isModalOpen"
      @close="isModalOpen = false"
      @acknowledge="handleAcknowledge"
      @resolve="handleResolve"
      @dismiss="handleDismiss"
      @reopen="handleReopen"
      @remediation-executed="handleRemediationExecuted"
    />
  </div>
</template>
