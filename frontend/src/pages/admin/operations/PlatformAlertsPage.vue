<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { platformOperationsService, type PlatformAlertItem } from '@/services/platformOperationsService';

const loading = ref(false);
const error = ref<string | null>(null);
const actionMessage = ref<string | null>(null);

const alerts = ref<PlatformAlertItem[]>([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

const filters = ref({
  status: 'active',
  severity: '',
  app_key: '',
  page: 1,
});

const selectedAlert = ref<PlatformAlertItem | null>(null);
const noteInput = ref('');
const actionModalType = ref<'acknowledge' | 'resolve' | null>(null);

const fetchAlerts = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await platformOperationsService.getAlerts({
      status: filters.value.status || undefined,
      severity: filters.value.severity || undefined,
      app_key: filters.value.app_key || undefined,
      page: filters.value.page,
    });
    alerts.value = res.data;
    pagination.value = {
      current_page: res.current_page,
      last_page: res.last_page,
      total: res.total,
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to fetch platform alerts.';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  filters.value.page = 1;
  fetchAlerts();
};

const openActionModal = (alert: PlatformAlertItem, type: 'acknowledge' | 'resolve') => {
  selectedAlert.value = alert;
  actionModalType.value = type;
  noteInput.value = '';
};

const closeActionModal = () => {
  selectedAlert.value = null;
  actionModalType.value = null;
  noteInput.value = '';
};

const submitActionModal = async () => {
  if (!selectedAlert.value || !actionModalType.value) return;
  loading.value = true;
  error.value = null;
  actionMessage.value = null;
  try {
    if (actionModalType.value === 'acknowledge') {
      await platformOperationsService.acknowledgeAlert(selectedAlert.value.id, noteInput.value);
      actionMessage.value = `Alert #${selectedAlert.value.id} acknowledged successfully.`;
    } else {
      await platformOperationsService.resolveAlert(selectedAlert.value.id, noteInput.value);
      actionMessage.value = `Alert #${selectedAlert.value.id} resolved successfully.`;
    }
    closeActionModal();
    fetchAlerts();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Action failed.';
  } finally {
    loading.value = false;
  }
};

const changePage = (newPage: number) => {
  if (newPage >= 1 && newPage <= pagination.value.last_page) {
    filters.value.page = newPage;
    fetchAlerts();
  }
};

const getSeverityClass = (sev: string) => {
  switch (sev?.toLowerCase()) {
    case 'critical':
      return 'bg-red-600 text-white';
    case 'warning':
      return 'bg-amber-500 text-white';
    case 'info':
    default:
      return 'bg-blue-600 text-white';
  }
};

const getStatusClass = (st: string) => {
  switch (st?.toLowerCase()) {
    case 'active':
      return 'bg-red-100 text-red-800 border-red-200';
    case 'acknowledged':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'resolved':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200';
  }
};

onMounted(() => {
  fetchAlerts();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Operational Alerts</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Monitor system health breaches, cron heartbeat timeouts, and queue failures.
        </p>
      </div>
      <button
        @click="fetchAlerts"
        :disabled="loading"
        class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-60 cursor-pointer self-start sm:self-auto"
      >
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>

    <!-- Feedback messages -->
    <div v-if="actionMessage" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium">
      {{ actionMessage }}
    </div>
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <!-- Filter bar -->
    <div class="bg-white border border-neutral-ivory rounded-xl p-4 shadow-soft flex flex-wrap items-center gap-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Status</label>
        <select
          v-model="filters.status"
          @change="applyFilters"
          class="px-3 py-1.5 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="acknowledged">Acknowledged</option>
          <option value="resolved">Resolved</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Severity</label>
        <select
          v-model="filters.severity"
          @change="applyFilters"
          class="px-3 py-1.5 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
        >
          <option value="">All Severities</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="critical">Critical</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">App Key</label>
        <input
          v-model="filters.app_key"
          @keyup.enter="applyFilters"
          placeholder="e.g. ems, platform"
          class="px-3 py-1.5 text-sm rounded-lg border border-neutral-ivory bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
        />
      </div>
    </div>

    <!-- Alerts Container -->
    <div v-if="loading && alerts.length === 0" class="flex justify-center py-16">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="alerts.length === 0" class="bg-white border border-neutral-ivory rounded-xl p-8 text-center text-neutral-muted shadow-soft">
      No alerts found for the current filter criteria.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="alert in alerts"
        :key="alert.id"
        class="bg-white border border-neutral-ivory rounded-xl p-5 shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4"
      >
        <div class="space-y-2 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span :class="getSeverityClass(alert.severity)" class="px-2.5 py-0.5 text-xs font-bold uppercase rounded-md">
              {{ alert.severity }}
            </span>
            <span :class="getStatusClass(alert.status)" class="px-2.5 py-0.5 text-xs font-bold uppercase rounded-full border">
              {{ alert.status }}
            </span>
            <span class="px-2.5 py-0.5 text-xs font-mono font-bold rounded-md bg-neutral-background border border-neutral-ivory text-neutral-black">
              {{ alert.app_key }}
            </span>
            <span class="text-xs text-neutral-muted font-mono ml-auto md:ml-0">
              {{ new Date(alert.created_at).toLocaleString() }}
            </span>
          </div>

          <h3 class="text-base font-semibold text-neutral-black">{{ alert.title }}</h3>
          <p class="text-sm text-neutral-muted">{{ alert.message }}</p>

          <!-- Audit details of acknowledgement/resolution -->
          <div v-if="alert.acknowledged_at || alert.resolved_at" class="text-xs font-mono text-neutral-muted pt-2 border-t border-neutral-ivory/40 flex flex-wrap gap-4">
            <span v-if="alert.acknowledged_at">
              Acknowledged: {{ new Date(alert.acknowledged_at).toLocaleString() }}
              <template v-if="alert.acknowledged_by"> by {{ alert.acknowledged_by.name || alert.acknowledged_by.first_name || ('User #' + alert.acknowledged_by.id) }}</template>
            </span>
            <span v-if="alert.resolved_at">
              Resolved: {{ new Date(alert.resolved_at).toLocaleString() }}
              <template v-if="alert.resolved_by"> by {{ alert.resolved_by.name || alert.resolved_by.first_name || ('User #' + alert.resolved_by.id) }}</template>
            </span>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
          <button
            v-if="alert.status === 'active'"
            @click="openActionModal(alert, 'acknowledge')"
            class="px-3 py-1.5 text-xs font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 rounded-lg border border-amber-200 transition cursor-pointer"
          >
            Acknowledge
          </button>
          <button
            v-if="alert.status !== 'resolved'"
            @click="openActionModal(alert, 'resolve')"
            class="px-3 py-1.5 text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition cursor-pointer"
          >
            Resolve
          </button>
        </div>
      </div>

      <!-- Pagination Footer -->
      <div v-if="pagination.total > 0" class="p-4 bg-white border border-neutral-ivory rounded-xl flex items-center justify-between shadow-soft">
        <span class="text-xs text-neutral-muted font-medium">
          Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total alerts)
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

    <!-- Action Modal -->
    <div v-if="actionModalType && selectedAlert" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <h3 class="text-lg font-bold text-neutral-black capitalize">
          {{ actionModalType }} Alert #{{ selectedAlert.id }}
        </h3>
        <p class="text-sm text-neutral-muted">
          {{ selectedAlert.title }}
        </p>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">
            Notes / Rationale (Optional)
          </label>
          <textarea
            v-model="noteInput"
            rows="3"
            placeholder="Add operational notes..."
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-ivory focus:outline-none focus:ring-2 focus:ring-primary/20"
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            @click="closeActionModal"
            class="px-4 py-2 text-xs font-bold text-neutral-muted hover:text-neutral-black bg-neutral-background rounded-xl cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitActionModal"
            :disabled="loading"
            class="px-4 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-hover rounded-xl cursor-pointer shadow-soft disabled:opacity-50"
          >
            Confirm {{ actionModalType }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
