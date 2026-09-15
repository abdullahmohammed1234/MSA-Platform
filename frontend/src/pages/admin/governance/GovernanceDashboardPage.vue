<template>
  <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Governance & Operational Continuity</h1>
          <p class="text-sm text-slate-500 mt-1">
            Accountability, audit explorer, data integrity diagnostics, and continuity telemetry.
          </p>
        </div>

        <!-- Actions & Period Filter -->
        <div class="flex items-center gap-3">
          <select
            v-model="selectedPeriod"
            @change="fetchOverview"
            class="text-xs px-3 py-2 border border-slate-300 rounded-lg bg-white font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="today">Today</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
            <option value="this_year">This Year</option>
          </select>

          <!-- Export Report Button -->
          <a
            :href="exportCsvUrl"
            target="_blank"
            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-lg transition shadow-sm flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Report (CSV)
          </a>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-16 text-center text-slate-500 text-sm">
        Loading governance telemetry & operational continuity state...
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-sm">
        {{ error }}
      </div>

      <!-- Main Dashboard Grid -->
      <div v-else-if="overview" class="space-y-6">
        <!-- Readiness Score Card -->
        <GovernanceReadinessCard
          :score="overview.governance_score"
          :level="overview.score_level"
          :drivers="overview.score_drivers"
        />

        <!-- Top Section Grid: Change Accountability & Audit Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <CriticalChangesPanel :items="overview.change_accountability" />
          <AuditActivityPanel :logs="overview.audit_activity.recent_logs" />
        </div>

        <!-- Middle Section Grid: Data Integrity & Continuity Readiness -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <IntegrityStatusPanel :result="overview.integrity_status" />
          <ContinuityReadinessPanel :readiness="overview.continuity_readiness" />
        </div>

        <!-- Bottom Section: Incident Timeline Reconstruction Explorer -->
        <IncidentTimelinePanel />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { governanceService, type GovernanceOverviewData } from '@/services/governanceService';

import GovernanceReadinessCard from '@/components/governance/GovernanceReadinessCard.vue';
import AuditActivityPanel from '@/components/governance/AuditActivityPanel.vue';
import CriticalChangesPanel from '@/components/governance/CriticalChangesPanel.vue';
import IncidentTimelinePanel from '@/components/governance/IncidentTimelinePanel.vue';
import IntegrityStatusPanel from '@/components/governance/IntegrityStatusPanel.vue';
import ContinuityReadinessPanel from '@/components/governance/ContinuityReadinessPanel.vue';

const selectedPeriod = ref('7d');
const loading = ref(true);
const error = ref<string | null>(null);
const overview = ref<GovernanceOverviewData | null>(null);

const exportCsvUrl = computed(() => governanceService.getReportExportUrl(selectedPeriod.value, 'csv'));

async function fetchOverview() {
  loading.value = true;
  error.value = null;

  try {
    overview.value = await governanceService.getGovernanceOverview(selectedPeriod.value);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load governance overview.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchOverview();
});
</script>
