<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { staleCapturesService } from '@/services/ems/staleCapturesService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useToastStore } from '@/components/feedback/toast';
import type { StaleCapture, StaleCaptureResolutionStatus } from '@/types/ems/staleCaptures';

const route = useRoute();
const toast = useToastStore();
const { fieldError, generalError, handle, clear } = useEmsApiError();
const { canRefundPayments } = useEmsPermissions();

const capture = ref<StaleCapture | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const refundReason = ref('');
const resolveReason = ref('');
const refundBusy = ref(false);
const resolveBusy = ref(false);
const showRefundConfirm = ref(false);
const showResolveConfirm = ref(false);

const paymentUuid = computed(() => String(route.params.paymentUuid ?? ''));
const squarePaymentId = computed(() => String(route.params.squarePaymentId ?? ''));

const load = async () => {
  isLoading.value = true;
  error.value = null;
  clear();
  try {
    capture.value = await staleCapturesService.get(paymentUuid.value, squarePaymentId.value);
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(load);

const isUnresolved = computed(
  () => capture.value?.resolution_status === 'unresolved' || capture.value?.resolution_status === 'partially_refunded'
);

const canAct = computed(() => canRefundPayments.value && isUnresolved.value);

const statusLabel = (status: StaleCaptureResolutionStatus): string => {
  const map: Record<StaleCaptureResolutionStatus, string> = {
    unresolved: 'Unresolved',
    refunded: 'Refunded',
    partially_refunded: 'Partially refunded',
    resolved_no_refund: 'Resolved (no refund)',
  };
  return map[status] ?? status;
};

const formatMoney = (amount: number, currency: string) =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);

const formatDate = (value: string | null) => {
  if (!value) return '—';
  return new Date(value).toLocaleString();
};

const submitRefund = async () => {
  if (!capture.value || refundReason.value.trim().length < 3) return;
  if (
    !window.confirm(
      'This will refund the Square payment captured after the attendee cancelled. The EMS booking will remain cancelled. No ticket or inventory changes will occur. Continue?'
    )
  ) {
    return;
  }

  refundBusy.value = true;
  clear();
  try {
    const result = await staleCapturesService.refund(paymentUuid.value, squarePaymentId.value, {
      reason: refundReason.value.trim(),
    });
    capture.value = result.stale_capture;
    showRefundConfirm.value = false;
    refundReason.value = '';
    toast.success('Stale capture refund submitted to Square.');
  } catch (caught) {
    handle(caught);
  } finally {
    refundBusy.value = false;
  }
};

const submitResolve = async () => {
  if (!capture.value || resolveReason.value.trim().length < 3) return;

  resolveBusy.value = true;
  clear();
  try {
    capture.value = await staleCapturesService.resolveWithoutRefund(
      paymentUuid.value,
      squarePaymentId.value,
      { reason: resolveReason.value.trim() }
    );
    showResolveConfirm.value = false;
    resolveReason.value = '';
    toast.success('Stale capture resolved without refund.');
  } catch (caught) {
    handle(caught);
  } finally {
    resolveBusy.value = false;
  }
};
</script>

<template>
  <div>
    <EmsPageHeader
      title="Stale Capture Detail"
      description="Review a Square capture that arrived after the attendee cancelled checkout."
      back-to="/ems/stale-captures"
      back-label="Back to stale captures"
    />

    <EmsErrorState v-if="error" :message="error" @retry="load" />

    <div v-else-if="isLoading" class="rounded-xl border border-neutral-ivory bg-white p-8 text-center text-sm text-neutral-muted">
      Loading stale capture…
    </div>

    <template v-else-if="capture">
      <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <div class="flex gap-3">
          <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
          <div>
            <p class="font-semibold">STALE CAPTURE — BUYER CANCELLED</p>
            <p class="mt-1 leading-relaxed">
              The attendee cancelled this checkout before EMS received confirmation of capture. Square subsequently
              reported a capture. EMS did not issue a ticket and did not automatically refund the payment.
            </p>
          </div>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-neutral-ivory bg-white p-5">
          <h2 class="text-sm font-bold text-neutral-black">Booking</h2>
          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Attendee</dt>
              <dd class="text-right font-semibold">{{ capture.attendee_name ?? 'Unknown' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Email</dt>
              <dd class="text-right">{{ capture.attendee_email ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Event</dt>
              <dd class="text-right">{{ capture.event_name ?? 'Event unavailable' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Order</dt>
              <dd class="text-right font-mono text-xs">{{ capture.order_reference ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Buyer cancelled</dt>
              <dd class="text-right text-xs">{{ formatDate(capture.buyer_cancelled_at) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">EMS payment status</dt>
              <dd class="text-right">{{ capture.payment_status_label }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Registration status</dt>
              <dd class="text-right">{{ capture.registration_status_label ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Tickets issued</dt>
              <dd class="text-right">{{ capture.ticket_count }}</dd>
            </div>
          </dl>
        </section>

        <section class="rounded-xl border border-neutral-ivory bg-white p-5">
          <h2 class="text-sm font-bold text-neutral-black">Square capture</h2>
          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Amount</dt>
              <dd class="text-right font-semibold">{{ formatMoney(capture.checkout_amount, capture.currency) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Square payment ID</dt>
              <dd class="text-right font-mono text-xs">{{ capture.square_payment_id }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Square order ID</dt>
              <dd class="text-right font-mono text-xs">{{ capture.square_order_id ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Reported</dt>
              <dd class="text-right text-xs">{{ formatDate(capture.reported_at) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Source</dt>
              <dd class="text-right">{{ capture.source ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Resolution</dt>
              <dd class="text-right font-semibold">{{ statusLabel(capture.resolution_status) }}</dd>
            </div>
            <div v-if="capture.resolved_at" class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Resolved at</dt>
              <dd class="text-right text-xs">{{ formatDate(capture.resolved_at) }}</dd>
            </div>
            <div v-if="capture.resolved_by_name" class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Resolved by</dt>
              <dd class="text-right">{{ capture.resolved_by_name }}</dd>
            </div>
            <div v-if="capture.resolution_reason" class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Reason</dt>
              <dd class="text-right">{{ capture.resolution_reason }}</dd>
            </div>
            <div v-if="capture.amount_refunded != null" class="flex justify-between gap-4">
              <dt class="text-neutral-muted">Amount refunded</dt>
              <dd class="text-right">{{ formatMoney(capture.amount_refunded, capture.currency) }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <p v-if="generalError" class="mt-4 text-sm font-semibold text-secondary">{{ generalError }}</p>

      <div v-if="canAct" class="mt-6 flex flex-wrap gap-3">
        <Button :disabled="refundBusy || resolveBusy" @click="showRefundConfirm = true">Refund Capture</Button>
        <Button variant="outline" :disabled="refundBusy || resolveBusy" @click="showResolveConfirm = true">
          Resolve Without Refund
        </Button>
      </div>

      <div
        v-if="showRefundConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showRefundConfirm = false"
      >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-premium-md">
          <h3 class="text-lg font-semibold">Refund stale capture</h3>
          <p class="mt-2 text-sm text-neutral-muted">
            This will refund the Square payment captured after the attendee cancelled. The EMS booking will remain
            cancelled. No ticket or inventory changes will occur.
          </p>
          <Textarea
            v-model="refundReason"
            label="Reason"
            class="mt-4"
            :rows="3"
            placeholder="Reason (required, min 3 characters)"
          />
          <p v-if="fieldError('reason')" class="mt-1 text-xs text-secondary">{{ fieldError('reason') }}</p>
          <div class="mt-5 flex justify-end gap-2">
            <Button variant="ghost" @click="showRefundConfirm = false">Cancel</Button>
            <Button
              :disabled="refundBusy || refundReason.trim().length < 3"
              @click="submitRefund"
            >
              {{ refundBusy ? 'Submitting…' : 'Confirm Refund' }}
            </Button>
          </div>
        </div>
      </div>

      <div
        v-if="showResolveConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showResolveConfirm = false"
      >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-premium-md">
          <h3 class="text-lg font-semibold">Resolve without refund</h3>
          <p class="mt-2 text-sm text-neutral-muted">
            Record that this capture was reviewed and intentionally not refunded. EMS booking status will not change.
          </p>
          <Textarea
            v-model="resolveReason"
            label="Reason"
            class="mt-4"
            :rows="3"
            placeholder="Reason (required, min 3 characters)"
          />
          <p v-if="fieldError('reason')" class="mt-1 text-xs text-secondary">{{ fieldError('reason') }}</p>
          <div class="mt-5 flex justify-end gap-2">
            <Button variant="ghost" @click="showResolveConfirm = false">Cancel</Button>
            <Button
              :disabled="resolveBusy || resolveReason.trim().length < 3"
              @click="submitResolve"
            >
              {{ resolveBusy ? 'Submitting…' : 'Confirm Resolution' }}
            </Button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
