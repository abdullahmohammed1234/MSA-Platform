<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { sponsorshipService } from '@/services/sponsorship.service';
import { FileSignature, ArrowLeft, CreditCard, DollarSign } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const loading = ref(true);
const deal = ref<any>(null);

const isManualPayModalOpen = ref(false);
const manualPay = ref({
  payment_method: 'cheque',
  amount_cents: 0,
  reference_number: '',
  notes: '',
});

const loadDeal = async () => {
  const uuid = route.params.uuid as string;
  if (!uuid || uuid === 'create') {
    router.replace('/sponsorship/admin/sponsorships');
    return;
  }
  loading.value = true;
  try {
    const res = await sponsorshipService.getSponsorshipDetail(uuid);
    if (res.success) {
      deal.value = res.data;
    }
  } catch (err) {
    console.error('Failed to load sponsorship detail:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDeal();
});

const handleRecordManualPayment = async () => {
  try {
    const res = await sponsorshipService.recordManualPayment(deal.value.uuid, manualPay.value);
    if (res.success) {
      toast.success('Manual payment recorded.');
      isManualPayModalOpen.value = false;
      loadDeal();
    }
  } catch (err: any) {
    toast.error('Failed to record payment.');
  }
};

const handleSquareCheckout = async () => {
  try {
    const res = await sponsorshipService.createSquareCheckout(deal.value.uuid, deal.value.outstanding_cents || 5000);
    if (res.success && res.data?.checkout_url) {
      window.location.href = res.data.checkout_url;
    }
  } catch (err: any) {
    toast.error('Failed to generate Square payment link.');
  }
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
    <button
      @click="router.push('/sponsorship/admin/sponsorships')"
      class="text-xs font-bold text-neutral-muted hover:text-primary flex items-center gap-1.5 cursor-pointer"
    >
      <ArrowLeft class="w-4 h-4" />
      <span>Back to Sponsorship Deals</span>
    </button>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="deal" class="space-y-6">
      <!-- Deal Header -->
      <div class="bg-white rounded-3xl p-6 border border-neutral-ivory shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="h-14 w-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary font-bold text-xl shrink-0">
            <FileSignature class="w-7 h-7" />
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-neutral-black">{{ deal.title }}</h1>
              <span class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded bg-emerald-50 text-emerald-700">
                {{ deal.status }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted mt-0.5">
              {{ deal.organization?.display_name }} • Deal #{{ deal.sponsorship_number }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" @click="handleSquareCheckout">
            <CreditCard class="w-4 h-4 mr-2" />
            Square Checkout Link
          </Button>
          <Button variant="primary" @click="isManualPayModalOpen = true">
            <DollarSign class="w-4 h-4 mr-2" />
            Record Manual Payment
          </Button>
        </div>
      </div>

      <!-- Financial Metrics & Status Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Committed</span>
          <div class="mt-2 text-2xl font-extrabold text-neutral-black">
            {{ formatCurrency(deal.total_committed_cents) }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Paid</span>
          <div class="mt-2 text-2xl font-extrabold text-primary">
            {{ formatCurrency(deal.total_paid_cents) }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Outstanding Balance</span>
          <div class="mt-2 text-2xl font-extrabold text-red-600">
            {{ formatCurrency(deal.total_committed_cents - deal.total_paid_cents) }}
          </div>
        </div>
      </div>

      <!-- Payments & Deliverables Tabs / Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payments History -->
        <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft space-y-4">
          <h2 class="text-base font-bold text-neutral-black border-b border-neutral-ivory pb-3">Payment Receipts</h2>

          <div v-if="!deal.payments || deal.payments.length === 0" class="text-xs text-neutral-muted py-4 text-center">
            No payment receipts recorded yet.
          </div>

          <div v-else class="divide-y divide-neutral-ivory">
            <div v-for="p in deal.payments" :key="p.uuid" class="py-3 flex items-center justify-between text-xs">
              <div>
                <span class="font-bold text-neutral-black block">{{ p.payment_number }}</span>
                <span class="text-[10px] text-neutral-muted font-mono uppercase">{{ p.payment_method }} • {{ p.status }}</span>
              </div>
              <span class="font-extrabold text-emerald-700">{{ formatCurrency(p.amount_cents) }}</span>
            </div>
          </div>
        </div>

        <!-- Deliverables & Fulfillment Progress -->
        <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft space-y-4">
          <h2 class="text-base font-bold text-neutral-black border-b border-neutral-ivory pb-3">Sponsorship Deliverables</h2>

          <div v-if="!deal.deliverables || deal.deliverables.length === 0" class="text-xs text-neutral-muted py-4 text-center">
            No deliverables assigned.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="d in deal.deliverables"
              :key="d.uuid"
              class="p-3 bg-neutral-background rounded-xl border border-neutral-ivory flex items-center justify-between text-xs"
            >
              <div>
                <span class="font-bold text-neutral-black block">{{ d.title }}</span>
                <span class="text-[10px] text-neutral-muted uppercase font-mono">{{ d.deliverable_type }}</span>
              </div>
              <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
                {{ d.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Record Manual Payment Modal -->
      <div v-if="isManualPayModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
          <h2 class="text-lg font-bold text-neutral-black">Record Offline / Manual Payment</h2>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Payment Method</label>
              <select v-model="manualPay.payment_method" class="w-full px-3 py-2 border rounded-xl text-xs">
                <option value="cheque">Cheque</option>
                <option value="e_transfer">Interac e-Transfer</option>
                <option value="bank_transfer">Wire / Bank Transfer</option>
                <option value="cash">Cash</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Amount (Cents) *</label>
              <input v-model.number="manualPay.amount_cents" type="number" required class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Reference # / Cheque #</label>
              <input v-model="manualPay.reference_number" type="text" class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <Button variant="outline" @click="isManualPayModalOpen = false">Cancel</Button>
            <Button variant="primary" @click="handleRecordManualPayment">Save Receipt</Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
