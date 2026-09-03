<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { donationsService, type DonationItem } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const donations = ref<DonationItem[]>([]);
const search = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(false);

const selectedDonation = ref<DonationItem | null>(null);
const refundReason = ref('');
const isRefunding = ref(false);

onMounted(async () => {
  await fetchDonations();
});

const fetchDonations = async (page = 1) => {
  isLoading.value = true;
  try {
    const data = await donationsService.getDmsDonations({
      page,
      search: search.value || undefined,
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    });
    donations.value = data.donations || [];
    currentPage.value = data.meta?.current_page || 1;
    lastPage.value = data.meta?.last_page || 1;
    total.value = data.meta?.total || 0;
  } catch (error) {
    toast.error('Failed to load donations.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

let searchTimeout: any = null;
watch([search, statusFilter], () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchDonations(1);
  }, 350);
});

const handleRefund = async () => {
  if (!selectedDonation.value || !refundReason.value.trim()) {
    toast.warning('Please specify a reason for the refund.');
    return;
  }

  isRefunding.value = true;
  try {
    await donationsService.refundDonation(selectedDonation.value.uuid, refundReason.value.trim());
    toast.success('Donation refunded successfully via Square.');
    selectedDonation.value = null;
    refundReason.value = '';
    await fetchDonations(currentPage.value);
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to refund donation.');
  } finally {
    isRefunding.value = false;
  }
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Donation Records</h1>
      <p class="text-sm text-neutral-muted mt-1">Browse, filter, inspect Square payment details, and process refunds.</p>
    </div>

    <!-- Controls -->
    <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full max-w-2xl">
        <div class="relative max-w-xs w-full">
          <input
            v-model="search"
            type="text"
            placeholder="Search ref #, name, or email..."
            class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
          />
          <span class="absolute left-3 top-2.5 text-neutral-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
        </div>

        <div class="flex items-center gap-1 bg-neutral-background p-1 rounded-xl border border-neutral-ivory overflow-x-auto">
          <button
            type="button"
            v-for="st in [
              { id: 'all', label: 'All Statuses' },
              { id: 'paid', label: 'Paid' },
              { id: 'pending', label: 'Pending' },
              { id: 'refunded', label: 'Refunded' },
              { id: 'failed', label: 'Failed' },
            ]"
            :key="st.id"
            @click="statusFilter = st.id"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
              statusFilter === st.id
                ? 'bg-white text-primary shadow-xs border border-neutral-ivory/60 font-bold'
                : 'text-neutral-muted hover:text-primary'
            ]"
          >
            {{ st.label }}
          </button>
        </div>
      </div>

      <div class="text-xs text-neutral-muted whitespace-nowrap">
        Total: <span class="font-bold text-neutral-black">{{ total }}</span> records
      </div>
    </div>

    <!-- Donations Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
        <p class="text-xs text-neutral-muted">Loading donation records...</p>
      </div>

      <div v-else-if="donations.length === 0" class="py-20 text-center text-neutral-muted italic">
        No donation records match the selected search and filter criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-4">Ref Number</th>
              <th class="px-6 py-4">Donor Information</th>
              <th class="px-6 py-4">Amount</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4">Square Payment ID</th>
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/30 text-xs">
            <tr v-for="d in donations" :key="d.uuid" class="hover:bg-neutral-background/30 transition-colors">
              <td class="px-6 py-4 font-mono font-bold text-neutral-black">{{ d.donation_number }}</td>
              <td class="px-6 py-4">
                <div class="font-semibold text-neutral-black">
                  <span :class="{'italic text-neutral-muted': d.is_anonymous}">
                    {{ d.is_anonymous ? 'Anonymous' : d.donor_name }}
                  </span>
                </div>
                <div class="text-neutral-muted text-[11px] font-mono mt-0.5">{{ d.donor_email }}</div>
              </td>
              <td class="px-6 py-4 font-bold text-primary">${{ (d.amount_cents / 100).toFixed(2) }} {{ d.currency }}</td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    'text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider',
                    d.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : d.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                  ]"
                >
                  {{ d.status }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono text-neutral-muted text-[11px]">{{ d.square_payment_id || '—' }}</td>
              <td class="px-6 py-4 text-neutral-muted font-mono whitespace-nowrap">{{ new Date(d.created_at).toLocaleDateString() }}</td>
              <td class="px-6 py-4 text-right">
                <button
                  @click="selectedDonation = d"
                  class="px-2.5 py-1 text-xs font-semibold text-primary border border-neutral-ivory rounded-lg hover:bg-neutral-background cursor-pointer"
                >
                  Inspect
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="px-6 py-4 border-t border-neutral-ivory/50 flex justify-between items-center text-xs">
        <span class="text-neutral-muted">Page {{ currentPage }} of {{ lastPage }}</span>
        <div class="flex gap-2">
          <button
            @click="fetchDonations(currentPage - 1)"
            :disabled="currentPage === 1 || isLoading"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Previous
          </button>
          <button
            @click="fetchDonations(currentPage + 1)"
            :disabled="currentPage === lastPage || isLoading"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Detail & Refund Drawer Modal -->
    <div v-if="selectedDonation" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-6">
        <div class="flex justify-between items-start pb-3 border-b border-neutral-ivory">
          <div>
            <h3 class="text-lg font-bold text-neutral-black">Donation Details</h3>
            <p class="text-xs font-mono text-primary mt-0.5">{{ selectedDonation.donation_number }}</p>
          </div>
          <button @click="selectedDonation = null" class="text-neutral-muted hover:text-black text-sm">✕</button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Donor Name:</span>
            <span class="font-bold text-neutral-black">{{ selectedDonation.donor_name }} ({{ selectedDonation.is_anonymous ? 'Anonymous' : 'Public' }})</span>
          </div>
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Donor Email:</span>
            <span class="font-mono text-neutral-black">{{ selectedDonation.donor_email }}</span>
          </div>
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Amount:</span>
            <span class="font-bold text-primary">${{ (selectedDonation.amount_cents / 100).toFixed(2) }} {{ selectedDonation.currency }}</span>
          </div>
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Square Checkout ID:</span>
            <span class="font-mono text-neutral-black">{{ selectedDonation.square_checkout_id || '—' }}</span>
          </div>
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Square Order ID:</span>
            <span class="font-mono text-neutral-black">{{ selectedDonation.square_order_id || '—' }}</span>
          </div>
          <div class="flex justify-between py-1 border-b border-neutral-ivory/40">
            <span class="text-neutral-muted">Square Payment ID:</span>
            <span class="font-mono text-neutral-black">{{ selectedDonation.square_payment_id || '—' }}</span>
          </div>
          <div v-if="selectedDonation.dedication" class="py-1">
            <span class="text-neutral-muted block mb-1">Dedication Note:</span>
            <p class="p-2.5 bg-neutral-background rounded-xl italic text-neutral-black">{{ selectedDonation.dedication }}</p>
          </div>
        </div>

        <!-- Refund Form Section -->
        <div v-if="selectedDonation.status === 'paid'" class="bg-red-50/50 p-4 rounded-2xl border border-red-200/60 space-y-3">
          <h4 class="text-xs font-bold text-red-700 uppercase tracking-wider">Process Square Refund</h4>
          <input
            v-model="refundReason"
            type="text"
            placeholder="Reason for refunding this donation..."
            class="w-full px-3 py-2 text-xs rounded-xl border border-red-200 focus:outline-none bg-white"
          />
          <button
            @click="handleRefund"
            :disabled="isRefunding || !refundReason.trim()"
            class="w-full py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all cursor-pointer disabled:opacity-50"
          >
            {{ isRefunding ? 'Refunding via Square...' : 'Confirm Refund' }}
          </button>
        </div>

        <div class="flex justify-end">
          <button @click="selectedDonation = null" class="px-4 py-2 bg-neutral-ivory text-neutral-black text-xs font-bold rounded-xl hover:bg-neutral-background cursor-pointer">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
