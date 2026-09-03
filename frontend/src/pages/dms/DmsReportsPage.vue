<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { donationsService } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const selectedYear = ref(new Date().getFullYear());
const reportsData = ref<any>(null);
const isLoading = ref(true);
const isReconciling = ref(false);

onMounted(async () => {
  await fetchReports();
});

const fetchReports = async () => {
  isLoading.value = true;
  try {
    const data = await donationsService.getDmsReports(selectedYear.value);
    reportsData.value = data;
  } catch (error) {
    toast.error('Failed to load reports.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const handleExportCsv = () => {
  const exportUrl = donationsService.getExportCsvUrl();
  window.open(exportUrl, '_blank');
};

const handleReconcile = async () => {
  isReconciling.value = true;
  try {
    const res = await donationsService.reconcileDms();
    toast.success(res.message || 'Reconciliation run complete.');
    await fetchReports();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Reconciliation failed.');
  } finally {
    isReconciling.value = false;
  }
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Financial Reports & Exports</h1>
        <p class="text-sm text-neutral-muted mt-1">Monthly breakdown of donation revenue, CSV data export, and Square reconciliation.</p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="handleReconcile"
          :disabled="isReconciling"
          class="px-3.5 py-2 text-xs font-bold rounded-xl border border-neutral-ivory hover:bg-neutral-background text-neutral-muted hover:text-primary transition-all cursor-pointer disabled:opacity-50"
        >
          {{ isReconciling ? 'Reconciling...' : 'Run Square Reconciliation' }}
        </button>

        <button
          @click="handleExportCsv"
          class="px-4 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer"
        >
          Export CSV Roster ↓
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
      <p class="text-xs text-neutral-muted">Generating financial reports...</p>
    </div>

    <template v-else-if="reportsData">
      <!-- Status Breakdown Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Paid Count</span>
          <div class="mt-1 text-xl font-bold text-emerald-600">{{ reportsData.status_breakdown?.paid || 0 }}</div>
        </div>
        <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Pending Count</span>
          <div class="mt-1 text-xl font-bold text-amber-500">{{ reportsData.status_breakdown?.pending || 0 }}</div>
        </div>
        <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Refunded Count</span>
          <div class="mt-1 text-xl font-bold text-rose-600">{{ reportsData.status_breakdown?.refunded || 0 }}</div>
        </div>
        <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Failed Count</span>
          <div class="mt-1 text-xl font-bold text-neutral-500">{{ reportsData.status_breakdown?.failed || 0 }}</div>
        </div>
      </div>

      <!-- Monthly Reports Table -->
      <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6 space-y-4">
        <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/50">
          <h2 class="text-base font-bold text-neutral-black">Monthly Contribution Revenue ({{ reportsData.year }})</h2>
          <select
            v-model="selectedYear"
            @change="fetchReports"
            class="px-3 py-1.5 text-xs rounded-xl border border-neutral-ivory focus:outline-none bg-neutral-background cursor-pointer"
          >
            <option :value="2026">2026</option>
            <option :value="2025">2025</option>
          </select>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
                <th class="px-4 py-3">Month</th>
                <th class="px-4 py-3">Donations Count</th>
                <th class="px-4 py-3 text-right">Total Revenue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/30 text-xs">
              <tr v-for="m in reportsData.monthly_reports" :key="m.month_number" class="hover:bg-neutral-background/30 transition-colors">
                <td class="px-4 py-3 font-semibold text-neutral-black">{{ m.month }}</td>
                <td class="px-4 py-3 font-bold text-neutral-black">{{ m.count }}</td>
                <td class="px-4 py-3 font-bold text-emerald-600 text-right">{{ m.formatted_amount }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
