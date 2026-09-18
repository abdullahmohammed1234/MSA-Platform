<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Loans & Circulation Records</h1>
        <p class="text-neutral-muted text-sm mt-1">Monitor active borrowings, overdue items, and execute administrative return overrides.</p>
      </div>

      <button
        @click="showScannerPanel = !showScannerPanel"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl transition-colors text-sm shadow-soft cursor-pointer"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
        <span>{{ showScannerPanel ? 'Hide Return Scanner' : 'Quick Return Scanner' }}</span>
      </button>
    </div>

    <!-- Quick Return & Circulation Scanner Panel -->
    <div v-if="showScannerPanel" class="bg-white rounded-2xl p-6 border border-amber-200 shadow-soft space-y-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-ivory pb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </div>
          <div>
            <h2 class="text-base font-bold text-neutral-black">Quick Return Barcode Scanner</h2>
            <p class="text-xs text-neutral-muted">Scan physical copy barcodes with a USB reader or camera to immediately process returns.</p>
          </div>
        </div>

        <button
          type="button"
          @click="showCamera = !showCamera"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-neutral-ivory bg-neutral-background hover:bg-neutral-ivory text-xs font-bold text-neutral-black transition-colors"
        >
          <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
          </svg>
          <span>{{ showCamera ? 'Hide Camera' : 'Use Camera Scanner' }}</span>
        </button>
      </div>

      <!-- Live Camera Scanner Viewport -->
      <div v-if="showCamera" class="max-w-md mx-auto">
        <CameraBarcodeScanner @scan-success="handleQuickScan" />
      </div>

      <!-- USB Barcode Reader & Input Form -->
      <form @submit.prevent="handleOverrideReturn" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
          <input
            ref="quickBarcodeInput"
            v-model="overrideBarcode"
            @keydown.enter.prevent="handleOverrideReturn"
            type="text"
            placeholder="Scan or enter book copy barcode (e.g. MLIB-C-000100)..."
            class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 font-mono text-sm text-neutral-black font-bold focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20 shadow-inner"
          />
          <span v-if="submittingOverride" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-amber-600 animate-pulse">
            Processing...
          </span>
        </div>

        <button
          type="submit"
          :disabled="submittingOverride || !overrideBarcode.trim()"
          class="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-sm transition-colors shadow-soft disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <span>Process Return</span>
        </button>
      </form>

      <!-- Return Result Alert Banner -->
      <div
        v-if="overrideMessage"
        :class="overrideSuccess ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'"
        class="p-4 rounded-xl border text-xs font-semibold flex items-center justify-between transition-all"
      >
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full" :class="overrideSuccess ? 'bg-emerald-500' : 'bg-rose-500'"></span>
          <span>{{ overrideMessage }}</span>
        </div>
        <button @click="overrideMessage = ''" class="text-neutral-muted hover:text-neutral-black">Dismiss</button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl p-4 border border-neutral-ivory shadow-soft flex flex-wrap gap-4 items-center">
      <div class="flex-1 min-w-[240px]">
        <input
          v-model="searchQuery"
          @input="debouncedFetch"
          type="text"
          placeholder="Search by copy barcode, member name, or email..."
          class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-2 text-sm text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
        />
      </div>

      <select
        v-model="selectedStatus"
        @change="fetchLoans"
        class="bg-neutral-background border border-neutral-ivory rounded-xl px-3 py-2 text-sm text-neutral-black focus:outline-none focus:border-primary font-medium shadow-sm"
      >
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="overdue">Overdue</option>
        <option value="returned">Returned</option>
        <option value="lost">Lost</option>
      </select>
    </div>

    <!-- Loans Table -->
    <div class="bg-white rounded-2xl border border-neutral-ivory shadow-soft overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-neutral-muted">Loading loans data...</div>
      <div v-else-if="loans.length === 0" class="p-8 text-center text-neutral-muted">No loan records found matching criteria.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm text-neutral-black">
          <thead class="bg-neutral-background text-xs uppercase text-neutral-muted font-bold tracking-wider border-b border-neutral-ivory">
            <tr>
              <th class="px-4 py-3">Copy Barcode</th>
              <th class="px-4 py-3">Book Title</th>
              <th class="px-4 py-3">Borrower</th>
              <th class="px-4 py-3">Borrowed Date</th>
              <th class="px-4 py-3">Due Date</th>
              <th class="px-4 py-3">Renewals</th>
              <th class="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr v-for="loan in loans" :key="loan.id" class="hover:bg-neutral-background/60 transition-colors">
              <td class="px-4 py-3 font-mono text-primary font-bold">{{ loan.copy?.barcode || 'N/A' }}</td>
              <td class="px-4 py-3 text-neutral-black font-bold">{{ loan.copy?.book?.title || 'Unknown Title' }}</td>
              <td class="px-4 py-3">
                <div class="font-bold text-neutral-black">{{ loan.member?.name }}</div>
                <div class="text-xs text-neutral-muted">{{ loan.member?.email }}</div>
              </td>
              <td class="px-4 py-3 text-neutral-muted">{{ formatDate(loan.borrowed_at) }}</td>
              <td class="px-4 py-3 font-bold" :class="isOverdue(loan) ? 'text-rose-600' : 'text-neutral-black'">
                {{ formatDate(loan.due_date) }}
              </td>
              <td class="px-4 py-3 text-neutral-muted">{{ loan.renewal_count || 0 }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(loan.status)" class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                  {{ loan.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import CameraBarcodeScanner from '@/components/mlibms/CameraBarcodeScanner.vue';

const loans = ref<any[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedStatus = ref('');

const showScannerPanel = ref(true);
const showCamera = ref(false);
const quickBarcodeInput = ref<HTMLInputElement | null>(null);

const overrideBarcode = ref('');
const submittingOverride = ref(false);
const overrideMessage = ref('');
const overrideSuccess = ref(false);

let timer: any = null;
const debouncedFetch = () => {
  clearTimeout(timer);
  timer = setTimeout(fetchLoans, 300);
};

const fetchLoans = async () => {
  loading.value = true;
  try {
    const res = await mlibmsAdminService.getLoans({
      query: searchQuery.value || undefined,
      status: selectedStatus.value || undefined,
    });
    loans.value = res.data || res;
  } catch (err) {
    console.error('Failed to load loans', err);
  } finally {
    loading.value = false;
  }
};

const handleQuickScan = (barcode: string) => {
  const cleanBarcode = barcode?.trim();
  if (!cleanBarcode || submittingOverride.value) return;

  overrideBarcode.value = cleanBarcode;
  handleOverrideReturn();
};

const handleOverrideReturn = async () => {
  const barcodeToProcess = overrideBarcode.value.trim();
  if (!barcodeToProcess || submittingOverride.value) return;

  submittingOverride.value = true;
  overrideMessage.value = '';
  try {
    const res = await mlibmsAdminService.overrideReturn(barcodeToProcess);
    overrideSuccess.value = true;
    overrideMessage.value = res.message || `Book copy ${barcodeToProcess} returned successfully.`;
    overrideBarcode.value = '';
    await fetchLoans();
  } catch (err: any) {
    overrideSuccess.value = false;
    overrideMessage.value = err.response?.data?.message || `Failed to return book copy ${barcodeToProcess}.`;
  } finally {
    submittingOverride.value = false;
    nextTick(() => {
      quickBarcodeInput.value?.focus();
    });
  }
};

const isOverdue = (loan: any) => {
  if (loan.status === 'returned') return false;
  return new Date(loan.due_date) < new Date();
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const statusBadgeClass = (status: string) => {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    case 'overdue':
      return 'bg-rose-50 text-rose-700 border border-rose-200';
    case 'returned':
      return 'bg-neutral-background text-neutral-muted border border-neutral-ivory';
    case 'lost':
      return 'bg-amber-50 text-amber-700 border border-amber-200';
    default:
      return 'bg-neutral-background text-neutral-muted border border-neutral-ivory';
  }
};

onMounted(() => {
  fetchLoans();
  nextTick(() => {
    quickBarcodeInput.value?.focus();
  });
});
</script>


