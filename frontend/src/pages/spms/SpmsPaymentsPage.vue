<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { sponsorshipService } from '@/services/sponsorship.service';

const loading = ref(true);
const payments = ref<any[]>([]);

const loadPayments = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getPayments();
    if (res.success) {
      payments.value = res.data || [];
    }
  } catch (err) {
    console.error('Failed to load payments:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadPayments();
});

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
  }).format((cents || 0) / 100);
};
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Payments Console</h1>
      <p class="text-xs text-neutral-muted mt-1">Audit log of Square online checkouts, cheques, e-transfers, and manual payments.</p>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="payments.length === 0" class="bg-white rounded-2xl p-12 text-center text-neutral-muted border border-neutral-ivory text-xs">
      No payment records found.
    </div>

    <div v-else class="bg-white rounded-2xl border border-neutral-ivory shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-ivory text-[10px] uppercase font-bold text-neutral-muted tracking-wider">
              <th class="py-3 px-4">Receipt Number</th>
              <th class="py-3 px-4">Sponsorship Deal</th>
              <th class="py-3 px-4">Method</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Amount</th>
              <th class="py-3 px-4">Recorded At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-xs">
            <tr v-for="p in payments" :key="p.uuid" class="hover:bg-neutral-background/50">
              <td class="py-3.5 px-4 font-mono font-bold text-neutral-black">
                {{ p.payment_number }}
              </td>
              <td class="py-3.5 px-4 font-bold text-neutral-black">
                {{ p.sponsorship?.title || 'Sponsorship' }}
              </td>
              <td class="py-3.5 px-4">
                <span class="text-[10px] font-mono uppercase bg-neutral-ivory px-2 py-0.5 rounded text-neutral-black">
                  {{ p.payment_method }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
                  {{ p.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 font-extrabold text-emerald-700">
                {{ formatCurrency(p.amount_cents) }}
              </td>
              <td class="py-3.5 px-4 text-neutral-muted font-mono text-[11px]">
                {{ new Date(p.created_at).toLocaleDateString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
