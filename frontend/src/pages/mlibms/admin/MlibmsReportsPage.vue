<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Circulation Analytics & Reports</h1>
        <p class="text-neutral-muted text-sm mt-1">Overview of library inventory performance, borrowing metrics, and CSV data export.</p>
      </div>

      <button
        @click="exportCsv"
        :disabled="exporting"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl transition-colors text-sm shadow-soft disabled:opacity-50"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 2 0 01-2 2z" />
        </svg>
        {{ exporting ? 'Exporting...' : 'Export Loans CSV' }}
      </button>
    </div>

    <!-- Stat Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <div>
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Total Titles</div>
          <div class="text-2xl font-black text-neutral-black">{{ stats.total_books ?? 0 }}</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-700 flex items-center justify-center border border-blue-500/20">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <div>
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Physical Copies</div>
          <div class="text-2xl font-black text-neutral-black">{{ stats.total_copies ?? 0 }}</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-teal-500/10 text-teal-700 flex items-center justify-center border border-teal-500/20">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Active Loans</div>
          <div class="text-2xl font-black text-neutral-black">{{ stats.active_loans ?? 0 }}</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-700 flex items-center justify-center border border-rose-500/20">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Overdue Items</div>
          <div class="text-2xl font-black text-neutral-black">{{ stats.overdue_loans ?? 0 }}</div>
        </div>
      </div>
    </div>

    <!-- Additional Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft space-y-4">
        <h3 class="text-lg font-bold text-neutral-black">Membership Summary</h3>
        <div class="space-y-3">
          <div class="flex justify-between items-center py-2 border-b border-neutral-ivory">
            <span class="text-neutral-muted text-sm font-medium">Total Library Members</span>
            <span class="font-bold text-neutral-black">{{ stats.total_members ?? 0 }}</span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-neutral-ivory">
            <span class="text-neutral-muted text-sm font-medium">Walk-in / Guest Borrowers</span>
            <span class="font-bold text-amber-700">{{ stats.guest_members ?? 0 }}</span>
          </div>
          <div class="flex justify-between items-center py-2">
            <span class="text-neutral-muted text-sm font-medium">Active Hold Reservations</span>
            <span class="font-bold text-primary">{{ stats.active_reservations ?? 0 }}</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft space-y-4">
        <h3 class="text-lg font-bold text-neutral-black">System Health & Compliance</h3>
        <p class="text-xs text-neutral-muted leading-relaxed">
          The MLibMS daily automated task enforces loss declarations, processes 2-day due reminders via idempotent dispatches, and expires uncollected holds at 08:00 daily.
        </p>
        <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory text-xs font-mono text-neutral-black space-y-1">
          <div><span class="text-primary font-bold">Status:</span> Scheduler Active (08:00 Daily)</div>
          <div><span class="text-primary font-bold">Reminder Timing:</span> 2 Calendar Days Prior</div>
          <div><span class="text-primary font-bold">Idempotency Guard:</span> Active (`reminder_sent_at`)</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';

const stats = ref<Record<string, any>>({});
const exporting = ref(false);

const fetchStats = async () => {
  try {
    const res = await mlibmsAdminService.getStats();
    stats.value = res.data || res;
  } catch (err) {
    console.error('Failed to load reports stats', err);
  }
};

const exportCsv = async () => {
  exporting.value = true;
  try {
    const blobData = await mlibmsAdminService.exportLoansCsv();
    const url = window.URL.createObjectURL(new Blob([blobData]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `mlibms-loans-report-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (err) {
    alert('Failed to export loans CSV report.');
  } finally {
    exporting.value = false;
  }
};

onMounted(() => {
  fetchStats();
});
</script>

