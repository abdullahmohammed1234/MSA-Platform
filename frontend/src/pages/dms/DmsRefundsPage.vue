<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { donationsService, type DonationItem } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const refundedDonations = ref<DonationItem[]>([]);
const isLoading = ref(true);

onMounted(async () => {
  await fetchRefunded();
});

const fetchRefunded = async () => {
  isLoading.value = true;
  try {
    const data = await donationsService.getDmsDonations({ status: 'refunded' });
    refundedDonations.value = data.donations || [];
  } catch (error) {
    toast.error('Failed to load refunded donations.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Refunds Console</h1>
      <p class="text-sm text-neutral-muted mt-1">Audit log of all processed donation refunds issued via Square API.</p>
    </div>

    <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
        <p class="text-xs text-neutral-muted">Loading refund history...</p>
      </div>

      <div v-else-if="refundedDonations.length === 0" class="py-20 text-center text-neutral-muted italic">
        No refunded donations found in history.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-4">Ref Number</th>
              <th class="px-6 py-4">Donor Information</th>
              <th class="px-6 py-4">Refunded Amount</th>
              <th class="px-6 py-4">Square Payment ID</th>
              <th class="px-6 py-4">Refunded Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/30 text-xs">
            <tr v-for="d in refundedDonations" :key="d.uuid" class="hover:bg-neutral-background/30 transition-colors">
              <td class="px-6 py-4 font-mono font-bold text-neutral-black">{{ d.donation_number }}</td>
              <td class="px-6 py-4">
                <div class="font-semibold text-neutral-black">{{ d.donor_name }}</div>
                <div class="text-neutral-muted text-[11px] font-mono mt-0.5">{{ d.donor_email }}</div>
              </td>
              <td class="px-6 py-4 font-bold text-rose-600">${{ (d.amount_cents / 100).toFixed(2) }} {{ d.currency }}</td>
              <td class="px-6 py-4 font-mono text-neutral-muted text-[11px]">{{ d.square_payment_id || '—' }}</td>
              <td class="px-6 py-4 text-neutral-muted font-mono whitespace-nowrap">{{ d.refunded_at ? new Date(d.refunded_at).toLocaleDateString() : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
