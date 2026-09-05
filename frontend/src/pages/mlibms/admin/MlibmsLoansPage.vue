<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Loans & Circulation Records</h1>
        <p class="text-neutral-muted text-sm mt-1">Monitor active borrowings, overdue items, and execute administrative return overrides.</p>
      </div>

      <button
        @click="showOverrideModal = true"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl transition-colors text-sm shadow-soft"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Admin Return Override
      </button>
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

    <!-- Admin Return Override Modal -->
    <div v-if="showOverrideModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white border border-neutral-ivory rounded-2xl p-6 max-w-md w-full shadow-soft space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-neutral-black">Staff Return Override</h3>
          <button @click="showOverrideModal = false" class="text-neutral-muted hover:text-neutral-black">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-xs text-neutral-muted leading-relaxed">
          Scan or enter a physical copy barcode to perform an administrative forced return (useful for misplaced books or walk-in guest returns).
        </p>

        <form @submit.prevent="handleOverrideReturn" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Copy Barcode</label>
            <input
              v-model="overrideBarcode"
              type="text"
              required
              placeholder="e.g. MLIB-C-000100"
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary font-mono text-sm shadow-sm"
            />
          </div>

          <div v-if="overrideMessage" :class="overrideSuccess ? 'text-emerald-700' : 'text-rose-700'" class="text-xs font-bold">
            {{ overrideMessage }}
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              @click="showOverrideModal = false"
              class="px-4 py-2 bg-neutral-background hover:bg-neutral-ivory text-neutral-black rounded-xl text-xs font-bold transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submittingOverride"
              class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-colors shadow-sm disabled:opacity-50"
            >
              {{ submittingOverride ? 'Processing...' : 'Complete Override Return' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';

const loans = ref<any[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedStatus = ref('');

const showOverrideModal = ref(false);
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

const handleOverrideReturn = async () => {
  if (!overrideBarcode.value) return;
  submittingOverride.value = true;
  overrideMessage.value = '';
  try {
    const res = await mlibmsAdminService.overrideReturn(overrideBarcode.value);
    overrideSuccess.value = true;
    overrideMessage.value = res.message || 'Book returned successfully via administrative override.';
    overrideBarcode.value = '';
    await fetchLoans();
  } catch (err: any) {
    overrideSuccess.value = false;
    overrideMessage.value = err.response?.data?.message || 'Failed to execute override return.';
  } finally {
    submittingOverride.value = false;
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
});
</script>

