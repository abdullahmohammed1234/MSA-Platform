<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  operationsService,
  type OperationalAlert,
  type OperationalActionExecution,
  type OperationalActionApproval,
} from '@/services/operationsService';
import OperationalAlertModal from '@/components/operations/OperationalAlertModal.vue';

const router = useRouter();

const activeTab = ref<'alerts' | 'executions' | 'approvals'>('alerts');

const loading = ref(true);
const alerts = ref<OperationalAlert[]>([]);
const executions = ref<OperationalActionExecution[]>([]);
const approvals = ref<OperationalActionApproval[]>([]);

const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

const selectedCategory = ref('');
const selectedStatus = ref('');
const searchQuery = ref('');

const activeAlertModal = ref<OperationalAlert | null>(null);
const isModalOpen = ref(false);
const activeExecutionModal = ref<OperationalActionExecution | null>(null);

const decisionReasonInput = ref('');
const activeApprovalDecision = ref<{ approval: OperationalActionApproval; type: 'approve' | 'reject' } | null>(null);
const approvalActionMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

const loadData = async (page = 1) => {
  loading.value = true;
  try {
    if (activeTab.value === 'alerts') {
      const params = {
        page,
        per_page: 20,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
        search: searchQuery.value || undefined,
      };
      const res = await operationsService.getAlerts(params);
      alerts.value = res.alerts.data;
      currentPage.value = res.alerts.current_page;
      totalPages.value = res.alerts.last_page;
      totalItems.value = res.alerts.total;
    } else if (activeTab.value === 'executions') {
      const params = {
        page,
        per_page: 20,
        status: selectedStatus.value || undefined,
      };
      const res = await operationsService.getExecutions(params);
      executions.value = res.executions.data;
      currentPage.value = res.executions.current_page;
      totalPages.value = res.executions.last_page;
      totalItems.value = res.executions.total;
    } else {
      const params = {
        page,
        per_page: 20,
        status: selectedStatus.value || undefined,
      };
      const res = await operationsService.getApprovals(params);
      approvals.value = res.approvals.data;
      currentPage.value = res.approvals.current_page;
      totalPages.value = res.approvals.last_page;
      totalItems.value = res.approvals.total;
    }
  } catch (err: any) {
    console.error('Failed to load history data:', err);
  } finally {
    loading.value = false;
  }
};

watch([activeTab, selectedCategory, selectedStatus], () => {
  loadData(1);
});

let searchTimeout: any = null;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadData(1);
  }, 350);
};

onMounted(() => {
  loadData(1);
});

const openAlertDetail = (alert: OperationalAlert) => {
  activeAlertModal.value = alert;
  isModalOpen.value = true;
};

const handleConfirmDecision = async () => {
  if (!activeApprovalDecision.value) return;
  const { approval, type } = activeApprovalDecision.value;

  try {
    if (type === 'approve') {
      await operationsService.approveRequest(approval.uuid, decisionReasonInput.value);
      showApprovalMessage('success', 'Remediation request approved successfully.');
    } else {
      await operationsService.rejectRequest(approval.uuid, decisionReasonInput.value);
      showApprovalMessage('success', 'Remediation request rejected.');
    }
    activeApprovalDecision.value = null;
    decisionReasonInput.value = '';
    await loadData(currentPage.value);
  } catch (err: any) {
    showApprovalMessage('error', err.response?.data?.message || 'Action failed.');
  }
};

const showApprovalMessage = (type: 'success' | 'error', text: string) => {
  approvalActionMessage.value = { type, text };
  setTimeout(() => {
    approvalActionMessage.value = null;
  }, 4000);
};

const formatDate = (isoStr?: string | null) => {
  if (!isoStr) return 'N/A';
  return new Date(isoStr).toLocaleString();
};

const statusBadgeClass = (status: string) => {
  switch (status) {
    case 'completed':
    case 'resolved':
    case 'approved':
      return 'bg-emerald-50 text-emerald-700 border-emerald-300';
    case 'open':
    case 'failed':
    case 'rejected':
      return 'bg-red-50 text-red-700 border-red-300';
    case 'acknowledged':
    case 'running':
    case 'pending':
      return 'bg-amber-50 text-amber-700 border-amber-300';
    case 'dismissed':
    case 'cancelled':
    case 'expired':
    case 'executed':
      return 'bg-gray-100 text-gray-600 border-gray-300';
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200';
  }
};
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <button @click="router.push('/admin/operations')" class="text-xs text-emerald-600 hover:underline font-semibold">
            ← Back to Operations Center
          </button>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
          Operational Audit & Governance Log
        </h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Historical records of detected alerts, remediation executions, and elevated approval decisions.
        </p>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200">
        <button
          @click="activeTab = 'alerts'"
          :class="['px-3 py-1.5 text-xs font-bold rounded-lg transition-all', activeTab === 'alerts' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900']"
        >
          Alert History
        </button>
        <button
          @click="activeTab = 'executions'"
          :class="['px-3 py-1.5 text-xs font-bold rounded-lg transition-all', activeTab === 'executions' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900']"
        >
          Remediation Executions
        </button>
        <button
          @click="activeTab = 'approvals'"
          :class="['px-3 py-1.5 text-xs font-bold rounded-lg transition-all', activeTab === 'approvals' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900']"
        >
          Approvals Queue
        </button>
      </div>
    </div>

    <!-- Notification Toast -->
    <div
      v-if="approvalActionMessage"
      :class="['p-4 rounded-xl text-xs font-semibold flex items-center justify-between border shadow-sm', approvalActionMessage.type === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-200' : 'bg-red-50 text-red-900 border-red-200']"
    >
      <span>{{ approvalActionMessage.text }}</span>
      <button @click="approvalActionMessage = null" class="text-gray-400">✕</button>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div v-if="activeTab === 'alerts'">
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Search Logs</label>
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="Title, rule, resolution reason..."
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px]"
          />
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Filter Status</label>
          <select
            v-model="selectedStatus"
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px] bg-white"
          >
            <option value="">All Statuses</option>
            <template v-if="activeTab === 'alerts'">
              <option value="resolved">Resolved</option>
              <option value="dismissed">Dismissed</option>
              <option value="acknowledged">Acknowledged</option>
              <option value="open">Open</option>
            </template>
            <template v-else-if="activeTab === 'executions'">
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="running">Running</option>
            </template>
            <template v-else>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="executed">Executed</option>
              <option value="expired">Expired</option>
            </template>
          </select>
        </div>

        <div v-if="activeTab === 'alerts'">
          <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Subsystem Category</label>
          <select
            v-model="selectedCategory"
            class="w-full text-xs p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none min-h-[44px] bg-white"
          >
            <option value="">All Categories</option>
            <option value="ems">EMS</option>
            <option value="donations">Donations</option>
            <option value="store">Store</option>
            <option value="mlibms">MLibMS</option>
            <option value="communications">Communications</option>
            <option value="volunteering">Volunteering</option>
            <option value="platform">Platform</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Alert History Table -->
    <div v-if="activeTab === 'alerts'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-gray-400 text-xs">
        Loading alert history...
      </div>

      <div v-else-if="alerts.length === 0" class="p-12 text-center text-gray-500 text-xs">
        No alert history records found matching criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50/80 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wider text-gray-400">
              <th class="py-3 px-4">Status & Category</th>
              <th class="py-3 px-4">Alert Details</th>
              <th class="py-3 px-4">Resolution Note</th>
              <th class="py-3 px-4">Timeline</th>
              <th class="py-3 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="alert in alerts" :key="alert.id" class="hover:bg-gray-50/60">
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <span :class="['px-2 py-0.5 text-[10px] font-bold capitalize rounded border block w-max mb-1', statusBadgeClass(alert.status)]">
                  {{ alert.status }}
                </span>
                <span class="text-[10px] font-mono text-gray-500 uppercase">{{ alert.category }}</span>
              </td>

              <td class="py-3.5 px-4 align-top max-w-sm">
                <div class="font-bold text-gray-900">{{ alert.title }}</div>
                <div class="text-[10px] font-mono text-gray-400">{{ alert.rule_key }}</div>
              </td>

              <td class="py-3.5 px-4 align-top max-w-xs text-[11px] text-gray-600">
                <div v-if="alert.resolution_reason" class="italic bg-gray-50 p-2 rounded border border-gray-100">
                  "{{ alert.resolution_reason }}"
                </div>
                <span v-else class="text-gray-400">—</span>
              </td>

              <td class="py-3.5 px-4 align-top whitespace-nowrap text-[10px] text-gray-500 space-y-0.5">
                <div>First: {{ formatDate(alert.first_detected_at) }}</div>
                <div v-if="alert.resolved_at" class="text-emerald-700 font-semibold">Resolved: {{ formatDate(alert.resolved_at) }}</div>
                <div v-if="alert.dismissed_at" class="text-gray-600">Dismissed: {{ formatDate(alert.dismissed_at) }}</div>
              </td>

              <td class="py-3.5 px-4 align-top text-right whitespace-nowrap">
                <button
                  @click="openAlertDetail(alert)"
                  class="px-2.5 py-1 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded"
                >
                  View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Remediation Action Executions Audit Log Table -->
    <div v-else-if="activeTab === 'executions'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-gray-400 text-xs">
        Loading remediation action executions...
      </div>

      <div v-else-if="executions.length === 0" class="p-12 text-center text-gray-500 text-xs">
        No remediation action executions recorded yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50/80 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wider text-gray-400">
              <th class="py-3 px-4">Status & Action Key</th>
              <th class="py-3 px-4">Target Alert</th>
              <th class="py-3 px-4">Summary / Outcome</th>
              <th class="py-3 px-4">Executed By</th>
              <th class="py-3 px-4">Execution Time</th>
              <th class="py-3 px-4 text-right">Snapshots</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="exec in executions" :key="exec.uuid" class="hover:bg-gray-50/60">
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border block w-max mb-1', statusBadgeClass(exec.status)]">
                  {{ exec.status }}
                </span>
                <span class="text-[10px] font-mono text-gray-600">{{ exec.action_key }}</span>
              </td>

              <td class="py-3.5 px-4 align-top max-w-xs">
                <div v-if="exec.alert" class="font-bold text-gray-900 text-[11px] line-clamp-1">#{{ exec.alert.id }} — {{ exec.alert.title }}</div>
                <div v-else class="font-mono text-gray-400 text-[10px]">Alert #{{ exec.alert_id }}</div>
              </td>

              <td class="py-3.5 px-4 align-top max-w-sm text-[11px]">
                <div :class="[exec.status === 'completed' ? 'text-emerald-900' : 'text-red-900 font-semibold']">
                  {{ exec.summary || exec.error_message || 'N/A' }}
                </div>
              </td>

              <td class="py-3.5 px-4 align-top whitespace-nowrap text-[11px]">
                <span v-if="exec.requester" class="font-semibold text-gray-800">{{ exec.requester.name }}</span>
                <span v-else class="text-gray-400">User #{{ exec.requested_by }}</span>
              </td>

              <td class="py-3.5 px-4 align-top whitespace-nowrap text-[10px] text-gray-500">
                {{ formatDate(exec.created_at) }}
              </td>

              <td class="py-3.5 px-4 align-top text-right whitespace-nowrap">
                <button
                  @click="activeExecutionModal = exec"
                  class="px-2.5 py-1 text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded"
                >
                  Inspect Snapshots
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Elevated Approval Queue Table -->
    <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-gray-400 text-xs">
        Loading remediation approval requests...
      </div>

      <div v-else-if="approvals.length === 0" class="p-12 text-center text-gray-500 text-xs">
        No elevated remediation approval requests found matching criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50/80 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wider text-gray-400">
              <th class="py-3 px-4">Status & Action</th>
              <th class="py-3 px-4">Target Alert</th>
              <th class="py-3 px-4">Requester</th>
              <th class="py-3 px-4">Request Reason</th>
              <th class="py-3 px-4">Requested At</th>
              <th class="py-3 px-4 text-right">Actions / Approver</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="appr in approvals" :key="appr.uuid" class="hover:bg-gray-50/60">
              <td class="py-3.5 px-4 align-top whitespace-nowrap">
                <span :class="['px-2 py-0.5 text-[10px] font-bold uppercase rounded border block w-max mb-1', statusBadgeClass(appr.status)]">
                  {{ appr.status }}
                </span>
                <span class="text-[10px] font-mono text-gray-700 font-semibold">{{ appr.action_key }}</span>
              </td>

              <td class="py-3.5 px-4 align-top max-w-xs">
                <div v-if="appr.alert" class="font-bold text-gray-900 text-[11px] line-clamp-1">#{{ appr.alert.id }} — {{ appr.alert.title }}</div>
                <div v-else class="font-mono text-gray-400 text-[10px]">Alert #{{ appr.operational_alert_id }}</div>
              </td>

              <td class="py-3.5 px-4 align-top whitespace-nowrap text-[11px]">
                <span v-if="appr.requested_by_user" class="font-semibold text-gray-900">{{ appr.requested_by_user.name }}</span>
                <span v-else class="text-gray-400">User #{{ appr.requested_by }}</span>
              </td>

              <td class="py-3.5 px-4 align-top max-w-xs text-[11px] text-gray-600">
                <div v-if="appr.request_reason" class="italic bg-gray-50 p-2 rounded border border-gray-100">
                  "{{ appr.request_reason }}"
                </div>
                <span v-else class="text-gray-400">—</span>
              </td>

              <td class="py-3.5 px-4 align-top whitespace-nowrap text-[10px] text-gray-500">
                {{ formatDate(appr.requested_at) }}
              </td>

              <td class="py-3.5 px-4 align-top text-right whitespace-nowrap">
                <div v-if="appr.status === 'pending'" class="flex items-center justify-end gap-1.5">
                  <button
                    @click="activeApprovalDecision = { approval: appr, type: 'approve' }"
                    class="px-2.5 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded shadow-sm"
                  >
                    Approve
                  </button>
                  <button
                    @click="activeApprovalDecision = { approval: appr, type: 'reject' }"
                    class="px-2.5 py-1 text-xs font-bold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded"
                  >
                    Reject
                  </button>
                </div>
                <div v-else class="text-[10px] text-gray-500">
                  <span v-if="appr.approved_by_user" class="text-emerald-700 font-semibold">Approved by {{ appr.approved_by_user.name }}</span>
                  <span v-else-if="appr.rejected_by_user" class="text-red-700 font-semibold">Rejected by {{ appr.rejected_by_user.name }}</span>
                  <span v-else>Decided</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs">
      <span class="text-gray-500">Page {{ currentPage }} of {{ totalPages }} ({{ totalItems }} total records)</span>
      <div class="flex items-center gap-2">
        <button
          @click="loadData(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1.5 font-semibold text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40"
        >
          Previous
        </button>
        <button
          @click="loadData(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="px-3 py-1.5 font-semibold text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Alert Modal Component -->
    <OperationalAlertModal
      :alert="activeAlertModal"
      :is-open="isModalOpen"
      @close="isModalOpen = false"
    />

    <!-- Execution Snapshot Inspector Modal -->
    <div
      v-if="activeExecutionModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-2xl w-full p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
          <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
            Remediation Execution Audit Snapshots
            <span :class="['px-2 py-0.5 text-[9px] uppercase font-bold rounded border', statusBadgeClass(activeExecutionModal.status)]">
              {{ activeExecutionModal.status }}
            </span>
          </h3>
          <button @click="activeExecutionModal = null" class="text-gray-400 hover:text-gray-600 text-sm">✕</button>
        </div>

        <div class="space-y-3 max-h-[60vh] overflow-y-auto text-xs">
          <div>
            <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">Action Key & UUID</span>
            <div class="font-mono text-gray-800 bg-gray-50 p-2 rounded border border-gray-100">
              {{ activeExecutionModal.action_key }} (UUID: {{ activeExecutionModal.uuid }})
            </div>
          </div>

          <div>
            <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">Summary</span>
            <div class="p-2.5 bg-emerald-50 text-emerald-900 rounded border border-emerald-200">
              {{ activeExecutionModal.summary || 'N/A' }}
            </div>
          </div>

          <div>
            <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">Before Execution Snapshot</span>
            <pre class="bg-gray-900 text-emerald-400 text-[10px] p-3 rounded overflow-x-auto font-mono">{{ JSON.stringify(activeExecutionModal.before_snapshot || {}, null, 2) }}</pre>
          </div>

          <div>
            <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">After Execution Snapshot</span>
            <pre class="bg-gray-900 text-emerald-400 text-[10px] p-3 rounded overflow-x-auto font-mono">{{ JSON.stringify(activeExecutionModal.after_snapshot || {}, null, 2) }}</pre>
          </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-gray-100">
          <button @click="activeExecutionModal = null" class="px-4 py-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
            Close Inspector
          </button>
        </div>
      </div>
    </div>

    <!-- Approval Decision Confirmation Dialog -->
    <div
      v-if="activeApprovalDecision"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-md w-full p-6 space-y-4">
        <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wider">
          Confirm Approval {{ activeApprovalDecision.type === 'approve' ? 'Grant' : 'Rejection' }}
        </h3>
        <p class="text-xs text-gray-600">
          Action Key: <span class="font-mono font-semibold text-gray-900">{{ activeApprovalDecision.approval.action_key }}</span>
        </p>

        <div>
          <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Decision Reason / Notes</label>
          <textarea
            v-model="decisionReasonInput"
            rows="3"
            placeholder="Enter reason or notes for this decision..."
            class="w-full text-xs p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          ></textarea>
        </div>

        <div class="flex justify-end gap-2">
          <button @click="activeApprovalDecision = null" class="px-3 py-1.5 text-xs font-semibold text-gray-600">Cancel</button>
          <button
            @click="handleConfirmDecision"
            :class="['px-4 py-1.5 text-xs font-bold text-white rounded-lg shadow-sm', activeApprovalDecision.type === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700']"
          >
            Confirm {{ activeApprovalDecision.type === 'approve' ? 'Approve' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
