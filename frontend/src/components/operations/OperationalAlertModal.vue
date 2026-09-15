<script setup lang="ts">
import { ref, watch } from 'vue';
import {
  operationsService,
  type OperationalAlert,
  type OperationalRemediationAction,
  type OperationalActionExecution,
  type OperationalActionApproval,
} from '@/services/operationsService';

const props = defineProps<{
  alert: OperationalAlert | null;
  isOpen: boolean;
  currentUserId?: number;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'acknowledge', alertId: number): void;
  (e: 'resolve', payload: { alertId: number; reason: string }): void;
  (e: 'dismiss', payload: { alertId: number; reason: string }): void;
  (e: 'reopen', alertId: number): void;
  (e: 'remediation-executed', execution: OperationalActionExecution): void;
  (e: 'approval-updated', approval: OperationalActionApproval): void;
}>();

const resolutionReason = ref('');
const actionType = ref<'resolve' | 'dismiss' | null>(null);

const remediationActions = ref<OperationalRemediationAction[]>([]);
const loadingActions = ref(false);
const executingActionKey = ref<string | null>(null);
const confirmActionKey = ref<string | null>(null);
const lastExecution = ref<OperationalActionExecution | null>(null);
const executionError = ref<string | null>(null);
const showSnapshots = ref(false);

const requestReasonInput = ref('');
const requestingApprovalKey = ref<string | null>(null);

const fetchActions = async () => {
  if (!props.alert) return;
  loadingActions.value = true;
  remediationActions.value = [];
  lastExecution.value = null;
  executionError.value = null;
  confirmActionKey.value = null;
  showSnapshots.value = false;
  requestingApprovalKey.value = null;

  try {
    const res = await operationsService.getActionsForAlert(props.alert.id);
    remediationActions.value = res.actions;
  } catch (err: any) {
    console.error('Failed to load remediation actions for alert:', err);
  } finally {
    loadingActions.value = false;
  }
};

watch(
  () => [props.isOpen, props.alert],
  ([newOpen, newAlert]) => {
    if (newOpen && newAlert) {
      resolutionReason.value = props.alert?.resolution_reason || '';
      actionType.value = null;
      fetchActions();
    }
  },
  { immediate: true }
);

const handleAcknowledge = () => {
  if (!props.alert) return;
  emit('acknowledge', props.alert.id);
};

const handleConfirmAction = () => {
  if (!props.alert || !actionType.value) return;

  if (actionType.value === 'resolve') {
    emit('resolve', { alertId: props.alert.id, reason: resolutionReason.value });
  } else if (actionType.value === 'dismiss') {
    emit('dismiss', { alertId: props.alert.id, reason: resolutionReason.value });
  }
  actionType.value = null;
};

const handleReopen = () => {
  if (!props.alert) return;
  emit('reopen', props.alert.id);
};

const triggerExecuteAction = async (action: OperationalRemediationAction) => {
  if (action.requires_confirmation && confirmActionKey.value !== action.key) {
    confirmActionKey.value = action.key;
    return;
  }

  if (!props.alert) return;

  executingActionKey.value = action.key;
  executionError.value = null;
  confirmActionKey.value = null;

  try {
    const res = await operationsService.executeAction(props.alert.id, action.key);
    lastExecution.value = res.execution;
    emit('remediation-executed', res.execution);
    await fetchActions();
  } catch (err: any) {
    const msg = err.response?.data?.message || err.message || 'Execution failed.';
    executionError.value = msg;
  } finally {
    executingActionKey.value = null;
  }
};

const handleRequestApproval = async (actionKey: string) => {
  if (!props.alert) return;
  try {
    const res = await operationsService.requestApproval(props.alert.id, actionKey, requestReasonInput.value);
    emit('approval-updated', res.approval);
    requestReasonInput.value = '';
    requestingApprovalKey.value = null;
    await fetchActions();
  } catch (err: any) {
    executionError.value = err.response?.data?.message || 'Failed to request approval.';
  }
};

const riskBadgeClass = (risk?: string) => {
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
</script>

<template>
  <div
    v-if="isOpen && alert"
    class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div
      class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-2xl w-full overflow-hidden transition-all transform"
      role="dialog"
      aria-modal="true"
    >
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div class="flex items-center gap-3">
          <span
            :class="['px-2.5 py-1 text-xs font-bold uppercase tracking-wider rounded-md border', severityBadgeClass(alert.severity)]"
          >
            {{ alert.severity }}
          </span>
          <span
            :class="['px-2.5 py-1 text-xs font-semibold capitalize rounded-md border', statusBadgeClass(alert.status)]"
          >
            {{ alert.status }}
          </span>
          <span class="text-xs font-mono text-gray-500 uppercase tracking-widest">
            {{ alert.category }}
          </span>
        </div>
        <button
          @click="emit('close')"
          class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          aria-label="Close dialog"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
        <div>
          <h2 class="text-xl font-bold text-gray-900 mb-1">
            {{ alert.title }}
          </h2>
          <p class="text-sm text-gray-600 leading-relaxed">
            {{ alert.description }}
          </p>
        </div>

        <!-- Alert Rule & Source Details -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-100 text-xs">
          <div>
            <span class="text-gray-400 uppercase font-semibold text-[10px] block">Rule Key</span>
            <span class="font-mono text-gray-800 font-medium">{{ alert.rule_key }}</span>
          </div>
          <div>
            <span class="text-gray-400 uppercase font-semibold text-[10px] block">Source Target</span>
            <span class="font-mono text-gray-800 font-medium">{{ alert.source_type }} #{{ alert.source_id }}</span>
          </div>
          <div>
            <span class="text-gray-400 uppercase font-semibold text-[10px] block">First Detected</span>
            <span class="text-gray-800 font-medium">{{ formatDate(alert.first_detected_at) }}</span>
          </div>
          <div>
            <span class="text-gray-400 uppercase font-semibold text-[10px] block">Last Detected</span>
            <span class="text-gray-800 font-medium">{{ formatDate(alert.last_detected_at) }}</span>
          </div>
        </div>

        <!-- Phase 25 & 26 Controlled Remediation & Governance Panel -->
        <div class="bg-gradient-to-r from-emerald-50/70 to-teal-50/70 p-4 rounded-xl border border-emerald-200/80 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-emerald-600 text-white rounded">Operational Governance</span>
              <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">Governed Remediation Actions</h3>
            </div>
            <span v-if="loadingActions" class="text-[10px] text-emerald-700 animate-pulse font-mono">Evaluating policy & preconditions...</span>
          </div>

          <div v-if="loadingActions" class="p-3 text-center text-xs text-gray-500">
            Checking governance rules and action handlers...
          </div>

          <div v-else-if="remediationActions.length === 0" class="p-3 text-xs text-gray-500 italic bg-white/60 rounded-lg border border-emerald-100">
            No registered automated remediation handlers available for rule '{{ alert.rule_key }}'. Please use the source interface to resolve manually.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="action in remediationActions"
              :key="action.key"
              class="bg-white p-4 rounded-lg border border-emerald-100 shadow-sm space-y-3"
            >
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span :class="['px-2 py-0.5 text-[9px] font-bold uppercase rounded border', riskBadgeClass(action.risk_level)]">
                      Risk: {{ action.risk_level }}
                    </span>
                    <h4 class="text-xs font-bold text-gray-900">
                      {{ action.name }}
                    </h4>
                    <span
                      v-if="action.governance"
                      :class="['px-2 py-0.5 text-[9px] font-bold uppercase rounded border', action.governance.allowed ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200']"
                    >
                      {{ action.governance.allowed ? 'Allowed' : (action.governance.requires_approval ? 'Approval Required' : 'Governance Blocked') }}
                    </span>
                  </div>
                  <p class="text-[11px] text-gray-600">
                    {{ action.description }}
                  </p>

                  <!-- Governance blocked reason note -->
                  <p v-if="action.governance && !action.governance.allowed && action.governance.blocked_reason" class="text-[10px] text-amber-700 bg-amber-50 p-2 rounded border border-amber-200 mt-2 font-mono">
                    Governance Note: {{ action.governance.blocked_reason }}
                  </p>
                </div>

                <!-- Execution Button / Approval Request Button -->
                <div class="flex items-center gap-2 self-end sm:self-center whitespace-nowrap">
                  <!-- Case 1: Allowed to execute directly -->
                  <button
                    v-if="!action.governance || action.governance.allowed"
                    @click="triggerExecuteAction(action)"
                    :disabled="!action.precondition.valid || executingActionKey === action.key"
                    :class="['px-3.5 py-1.5 text-xs font-bold text-white rounded-lg shadow-sm transition-all cursor-pointer min-h-[36px]', confirmActionKey === action.key ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700', (!action.precondition.valid || executingActionKey === action.key) ? 'opacity-40 cursor-not-allowed' : '']"
                  >
                    <span v-if="executingActionKey === action.key" class="flex items-center gap-1">
                      <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Executing...
                    </span>
                    <span v-else-if="confirmActionKey === action.key">
                      Confirm Execute
                    </span>
                    <span v-else>
                      Execute Remediation
                    </span>
                  </button>

                  <!-- Case 2: Requires Approval -->
                  <button
                    v-else-if="action.governance && action.governance.requires_approval && action.governance.approval_status === 'none'"
                    @click="requestingApprovalKey = action.key"
                    class="px-3.5 py-1.5 text-xs font-bold text-blue-800 bg-blue-100 hover:bg-blue-200 border border-blue-300 rounded-lg shadow-sm transition-all min-h-[36px]"
                  >
                    Request Approval
                  </button>

                  <!-- Case 3: Pending Approval -->
                  <span
                    v-else-if="action.governance && action.governance.approval_status === 'pending'"
                    class="px-3 py-1 text-xs font-bold text-amber-800 bg-amber-100 border border-amber-300 rounded-lg"
                  >
                    Pending Approval
                  </span>
                </div>
              </div>

              <!-- Request Approval Sub-form -->
              <div v-if="requestingApprovalKey === action.key" class="bg-blue-50 p-3 rounded-lg border border-blue-200 space-y-2">
                <span class="text-[10px] font-bold uppercase text-blue-900 block">Submit Elevated Remediation Approval Request</span>
                <input
                  v-model="requestReasonInput"
                  type="text"
                  placeholder="Enter reason or operational context for this request..."
                  class="w-full text-xs p-2 border border-blue-300 rounded bg-white text-gray-900"
                />
                <div class="flex justify-end gap-2">
                  <button @click="requestingApprovalKey = null" class="px-2.5 py-1 text-xs font-semibold text-gray-600">Cancel</button>
                  <button @click="handleRequestApproval(action.key)" class="px-3 py-1 text-xs font-bold text-white bg-blue-600 rounded">Submit Request</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Remediation Execution Result -->
          <div v-if="lastExecution" class="p-3 bg-emerald-100/90 text-emerald-900 rounded-lg text-xs space-y-2 border border-emerald-300">
            <div class="flex items-center justify-between font-bold">
              <span>Remediation Status: {{ lastExecution.status.toUpperCase() }}</span>
              <button @click="showSnapshots = !showSnapshots" class="text-[10px] text-emerald-800 underline">
                {{ showSnapshots ? 'Hide State Snapshots' : 'Inspect Audit Snapshots' }}
              </button>
            </div>
            <p>{{ lastExecution.summary }}</p>

            <div v-if="showSnapshots" class="space-y-2 pt-2 border-t border-emerald-200">
              <div>
                <span class="text-[10px] font-bold uppercase text-emerald-800 block mb-0.5">Before Snapshot</span>
                <pre class="bg-gray-900 text-emerald-400 text-[10px] p-2 rounded overflow-x-auto font-mono">{{ JSON.stringify(lastExecution.before_snapshot, null, 2) }}</pre>
              </div>
              <div>
                <span class="text-[10px] font-bold uppercase text-emerald-800 block mb-0.5">After Snapshot</span>
                <pre class="bg-gray-900 text-emerald-400 text-[10px] p-2 rounded overflow-x-auto font-mono">{{ JSON.stringify(lastExecution.after_snapshot, null, 2) }}</pre>
              </div>
            </div>
          </div>

          <div v-if="executionError" class="p-3 bg-red-100 text-red-900 rounded-lg text-xs border border-red-300">
            <span class="font-bold">Execution / Governance Error:</span> {{ executionError }}
          </div>
        </div>

        <!-- Additional Metadata -->
        <div v-if="alert.metadata && Object.keys(alert.metadata).length > 0" class="space-y-2">
          <h4 class="text-xs uppercase tracking-wider font-bold text-gray-500">Operational Context Metadata</h4>
          <pre class="bg-gray-900 text-emerald-400 text-xs p-3 rounded-lg overflow-x-auto font-mono">{{ JSON.stringify(alert.metadata, null, 2) }}</pre>
        </div>

        <!-- Lifecycle Event History -->
        <div class="border-t border-gray-100 pt-4 space-y-2 text-xs">
          <h4 class="uppercase tracking-wider font-bold text-gray-500 text-[10px]">Lifecycle History</h4>
          <div class="space-y-1.5 text-gray-600">
            <div v-if="alert.acknowledged_at" class="flex items-center justify-between">
              <span>Acknowledged on {{ formatDate(alert.acknowledged_at) }}</span>
              <span v-if="alert.acknowledged_by_user" class="font-semibold text-gray-800">By {{ alert.acknowledged_by_user.name }}</span>
            </div>
            <div v-if="alert.resolved_at" class="flex items-center justify-between">
              <span>Resolved on {{ formatDate(alert.resolved_at) }}</span>
              <span v-if="alert.resolved_by_user" class="font-semibold text-emerald-700">By {{ alert.resolved_by_user.name }}</span>
            </div>
            <div v-if="alert.dismissed_at" class="flex items-center justify-between">
              <span>Dismissed on {{ formatDate(alert.dismissed_at) }}</span>
              <span v-if="alert.dismissed_by_user" class="font-semibold text-gray-700">By {{ alert.dismissed_by_user.name }}</span>
            </div>
            <div v-if="alert.resolution_reason" class="mt-2 p-3 bg-amber-50 rounded-lg text-amber-900 border border-amber-200">
              <span class="font-bold text-[10px] uppercase block text-amber-700 mb-0.5">Resolution Note</span>
              {{ alert.resolution_reason }}
            </div>
          </div>
        </div>

        <!-- Action Reason Form -->
        <div v-if="actionType" class="bg-blue-50/60 p-4 rounded-lg border border-blue-200 space-y-3">
          <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider">
            Confirm {{ actionType === 'resolve' ? 'Resolution' : 'Dismissal' }}
          </h4>
          <textarea
            v-model="resolutionReason"
            rows="3"
            placeholder="Enter reason or operational notes for this decision..."
            class="w-full text-xs p-2.5 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white text-gray-900"
          ></textarea>
          <div class="flex justify-end gap-2">
            <button
              @click="actionType = null"
              class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg"
            >
              Cancel
            </button>
            <button
              @click="handleConfirmAction"
              :class="['px-4 py-1.5 text-xs font-bold text-white rounded-lg shadow-sm', actionType === 'resolve' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-700 hover:bg-gray-800']"
            >
              Confirm {{ actionType === 'resolve' ? 'Resolve' : 'Dismiss' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Footer Controls -->
      <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div>
          <a
            v-if="alert.action_url"
            :href="alert.action_url"
            target="_blank"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition-colors"
          >
            Open Source Interface
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="alert.status === 'open'"
            @click="handleAcknowledge"
            class="px-3.5 py-2 text-xs font-bold text-amber-800 bg-amber-100 hover:bg-amber-200 rounded-lg transition-colors"
          >
            Acknowledge
          </button>

          <button
            v-if="['open', 'acknowledged'].includes(alert.status) && !actionType"
            @click="actionType = 'resolve'"
            class="px-3.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition-colors"
          >
            Resolve Alert
          </button>

          <button
            v-if="['open', 'acknowledged'].includes(alert.status) && !actionType"
            @click="actionType = 'dismiss'"
            class="px-3.5 py-2 text-xs font-bold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors"
          >
            Dismiss
          </button>

          <button
            v-if="['resolved', 'dismissed'].includes(alert.status)"
            @click="handleReopen"
            class="px-3.5 py-2 text-xs font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors"
          >
            Reopen Alert
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
