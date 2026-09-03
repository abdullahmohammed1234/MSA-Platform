<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { sponsorshipService } from '@/services/sponsorship.service';
import { Download } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const loading = ref(true);
const metrics = ref<any>({});

onMounted(async () => {
  try {
    const res = await sponsorshipService.getDashboardMetrics();
    if (res.success) {
      metrics.value = res.data.metrics;
    }
  } catch (err) {
    console.error('Failed to load report metrics:', err);
  } finally {
    loading.value = false;
  }
});

const handleExportCsv = () => {
  const token = localStorage.getItem('token');
  window.open(`/api/v1/sponsorship/admin/reports/export?token=${token}`, '_blank');
};

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
  }).format((cents || 0) / 100);
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Reports & Analytics</h1>
        <p class="text-xs text-neutral-muted mt-1">Financial reconciliation, commitment metrics, and sanitized CSV exports.</p>
      </div>

      <Button variant="primary" @click="handleExportCsv">
        <Download class="w-4 h-4 mr-2" />
        Export CSV Audit Report
      </Button>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Committed</span>
          <div class="mt-2 text-2xl font-extrabold text-neutral-black">
            {{ formatCurrency(metrics.total_committed_cents) }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Collected</span>
          <div class="mt-2 text-2xl font-extrabold text-primary">
            {{ formatCurrency(metrics.total_collected_cents) }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Outstanding CAD</span>
          <div class="mt-2 text-2xl font-extrabold text-red-600">
            {{ formatCurrency(metrics.outstanding_cents) }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">In-Kind Estimated</span>
          <div class="mt-2 text-2xl font-extrabold text-neutral-black">
            {{ formatCurrency(metrics.total_in_kind_cents) }}
          </div>
        </div>
      </div>

      <div class="bg-white rounded-3xl border border-neutral-ivory p-8 shadow-soft space-y-4">
        <h2 class="text-lg font-bold text-neutral-black">CSV Financial Audit Export</h2>
        <p class="text-xs text-neutral-muted leading-relaxed">
          The export includes all sponsorship numbers, legal organization names, agreement titles, financial commitments, payment receipts, outstanding balances, in-kind valuations, and start/end dates. All string fields are sanitized against formula injection (`=`, `+`, `-`, `@`).
        </p>
        <Button variant="outline" @click="handleExportCsv">
          <Download class="w-4 h-4 mr-2" />
          Download Complete CSV Report
        </Button>
      </div>
    </div>
  </div>
</template>
