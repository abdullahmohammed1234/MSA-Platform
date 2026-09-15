<script setup lang="ts">
import { ref } from 'vue';
import { ShieldAlert, X, CheckCircle } from 'lucide-vue-next';
import { useToastStore } from '@/components/feedback/toast';
import client from '@/services/api';

const props = defineProps<{
  open: boolean;
  registration: {
    id: number;
    uuid: string;
    attendee_name: string;
    attendee_email: string;
    status: string;
    event?: { name: string };
  } | null;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'success'): void;
}>();

const toast = useToastStore();
const mode = ref<'override' | 'square'>('override');
const paymentMethod = ref('cash');
const referenceId = ref('');
const amount = ref<number | null>(null);
const reason = ref('');
const isSubmitting = ref(false);

const submitOverride = async () => {
  if (!props.registration) return;
  if (!reason.value.trim() || reason.value.trim().length < 5) {
    toast.error('Please enter a detailed administrative reason (min 5 characters).');
    return;
  }

  isSubmitting.value = true;
  try {
    if (mode.value === 'override') {
      await client.post('/ems/admin/payments/override', {
        registration_id: props.registration.id,
        payment_method: paymentMethod.value,
        reference_id: referenceId.value.trim() || undefined,
        amount: amount.value ?? undefined,
        reason: reason.value.trim(),
      });
      toast.success('Super-admin payment override completed and ticket issued.');
    } else {
      if (!referenceId.value.trim()) {
        toast.error('External Square Transaction ID is required for reconciliation.');
        isSubmitting.value = false;
        return;
      }
      await client.post('/ems/admin/payments/reconcile-external', {
        registration_id: props.registration.id,
        external_transaction_id: referenceId.value.trim(),
        payment_method: 'square_pos',
        amount: amount.value ?? undefined,
        reason: reason.value.trim(),
      });
      toast.success('External Square POS payment reconciled and ticket issued.');
    }

    emit('success');
    emit('close');
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Reconciliation failed.');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="open && registration" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-white rounded-2xl border border-neutral-ivory max-w-lg w-full p-6 space-y-6 shadow-premium">
      <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
        <div class="flex items-center gap-2">
          <ShieldAlert class="w-5 h-5 text-amber-600" />
          <h3 class="text-base font-bold text-neutral-black">Payment Override & Reconciliation</h3>
        </div>
        <button @click="emit('close')" class="p-1 rounded-lg text-neutral-muted hover:text-neutral-black hover:bg-neutral-background">
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="bg-neutral-background p-3.5 rounded-xl space-y-1 text-xs">
        <p><span class="font-bold text-neutral-muted">Participant:</span> {{ registration.attendee_name }} ({{ registration.attendee_email }})</p>
        <p><span class="font-bold text-neutral-muted">Event:</span> {{ registration.event?.name || '—' }}</p>
        <p><span class="font-bold text-neutral-muted">Current State:</span> <span class="uppercase font-mono text-amber-700 font-bold">{{ registration.status }}</span></p>
      </div>

      <!-- Mode Selection Tabs -->
      <div class="flex gap-2 p-1 bg-neutral-background rounded-xl border border-neutral-ivory text-xs font-bold">
        <button
          type="button"
          @click="mode = 'override'"
          :class="['flex-1 py-2 rounded-lg transition-all', mode === 'override' ? 'bg-white shadow-xs text-primary' : 'text-neutral-muted hover:text-neutral-black']"
        >
          Super-Admin Override
        </button>
        <button
          type="button"
          @click="mode = 'square'"
          :class="['flex-1 py-2 rounded-lg transition-all', mode === 'square' ? 'bg-white shadow-xs text-primary' : 'text-neutral-muted hover:text-neutral-black']"
        >
          Square POS Reconcile
        </button>
      </div>

      <form @submit.prevent="submitOverride" class="space-y-4 text-xs">
        <div v-if="mode === 'override'">
          <label class="block font-bold text-neutral-black mb-1">Payment Method *</label>
          <select v-model="paymentMethod" class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black font-medium focus:outline-none focus:border-primary">
            <option value="cash">In-Person Cash</option>
            <option value="cheque">Cheque</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="square_pos">Square POS Terminal</option>
            <option value="external_square">Square External Direct</option>
            <option value="other">Other Manual Method</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-neutral-black mb-1">
            {{ mode === 'square' ? 'Square Transaction / Reference ID *' : 'Transaction / Reference ID (Optional)' }}
          </label>
          <input
            v-model="referenceId"
            type="text"
            placeholder="e.g. SQ_POS_TXN_998811 or Cash receipt #1002"
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black font-mono focus:outline-none focus:border-primary"
          />
        </div>

        <div>
          <label class="block font-bold text-neutral-black mb-1">Administrative Reason (Required for Audit Trail) *</label>
          <textarea
            v-model="reason"
            rows="3"
            placeholder="Explain why this payment is being overridden/reconciled outside normal online checkout..."
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black focus:outline-none focus:border-primary"
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-neutral-ivory">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2.5 rounded-xl border border-neutral-ivory text-neutral-black font-bold hover:bg-neutral-background"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold flex items-center gap-1.5 shadow-soft disabled:opacity-50"
          >
            <CheckCircle class="w-4 h-4" />
            <span>{{ isSubmitting ? 'Reconciling...' : 'Confirm & Issue Ticket' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
