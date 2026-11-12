<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import ManualRegistrationModal from '@/components/ems/ManualRegistrationModal.vue';
import { operationsService } from '@/services/ems/operationsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EmsAttendee, EmsCancellationAudit } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const { handle } = useEmsApiError();
const {
  canCheckIn,
  canImportAttendees,
  canUndoCheckIn,
  canRefundPayments,
  canCancelRegistrations,
  canViewCancellationHistory,
} = useEmsPermissions();
const { getStatusStyle } = useEventFormatting();

const uuid = computed(() => route.params.uuid as string);
const attendees = ref<EmsAttendee[]>([]);
const selected = ref<Set<string>>(new Set());
const search = ref('');
const registrationStatus = ref('');
const paymentStatus = ref('');
const checkInStatus = ref('');
const source = ref('');
const sortBy = ref('registration_date');
const sortDirection = ref<'asc' | 'desc'>('desc');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);
const undoReason = ref('');
const undoTarget = ref<EmsAttendee | null>(null);
const refundTarget = ref<EmsAttendee | null>(null);
const refundReason = ref('');
const refundBusy = ref(false);
const isManualModalOpen = ref(false);

const cancelTarget = ref<EmsAttendee | null>(null);
const cancelReason = ref('');
const cancelIssueRefund = ref(false);
const cancelRefundAmount = ref<number | string>('');
const cancelBusy = ref(false);

const isHistoryModalOpen = ref(false);
const historyList = ref<EmsCancellationAudit[]>([]);
const isHistoryLoading = ref(false);
const historyError = ref<string | null>(null);

const statusOptions = [
  { value: '', label: 'All registration statuses' },
  { value: 'confirmed', label: 'Registered' },
  { value: 'awaiting_payment', label: 'Pending Payment' },
  { value: 'waitlisted', label: 'Waitlisted' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'refunded', label: 'Refunded' },
];

const paymentOptions = [
  { value: '', label: 'All payment statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'failed', label: 'Failed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'refunded', label: 'Refunded' },
];

const checkInOptions = [
  { value: '', label: 'All attendance statuses' },
  { value: 'not_checked_in', label: 'Not Checked In' },
  { value: 'checked_in', label: 'Attending' },
  { value: 'no_show', label: "Didn't come" },
];

const sourceOptions = [
  { value: '', label: 'All sources' },
  { value: 'ems', label: 'EMS' },
  { value: 'imported', label: 'Imported' },
  { value: 'walk_in', label: 'Walk-ins' },
  { value: 'square_online_store', label: 'Square Online Store' },
];

const sortOptions = [
  { value: 'name', label: 'Name' },
  { value: 'registration_date', label: 'Registration date' },
  { value: 'check_in_time', label: 'Check-in time' },
  { value: 'ticket_type', label: 'Ticket type' },
  { value: 'payment_status', label: 'Payment status' },
];

const load = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const result = await operationsService.listAttendees(uuid.value, {
      search: search.value || undefined,
      registration_status: registrationStatus.value || undefined,
      payment_status: paymentStatus.value || undefined,
      check_in_status: checkInStatus.value || undefined,
      source: source.value || undefined,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      page: page.value,
      per_page: 25,
    });
    attendees.value = result.items;
    page.value = result.pagination.current_page;
    lastPage.value = result.pagination.last_page;
    total.value = result.pagination.total;
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
};

const toggle = (id: string) => {
  const next = new Set(selected.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selected.value = next;
};

const toggleAll = () => {
  if (selected.value.size === attendees.value.length) {
    selected.value = new Set();
  } else {
    selected.value = new Set(attendees.value.map((a) => a.uuid));
  }
};

const confirmUndo = async () => {
  if (!undoTarget.value || !undoReason.value.trim()) return;
  try {
    await operationsService.undoCheckIn(uuid.value, {
      ticket_code: undoTarget.value.ticket_code ?? undefined,
      reason: undoReason.value.trim(),
    });
    undoTarget.value = null;
    undoReason.value = '';
    await load();
  } catch (caught) {
    handle(caught);
  }
};

const confirmRefund = async () => {
  if (!refundTarget.value?.payment_uuid) return;
  if (!window.confirm(`Refund ${refundTarget.value.attendee_name}'s payment? This cannot be undone from EMS.`)) {
    return;
  }
  refundBusy.value = true;
  try {
    await operationsService.refundPayment(refundTarget.value.payment_uuid, {
      reason: refundReason.value.trim() || 'EMS refund',
    });
    refundTarget.value = null;
    refundReason.value = '';
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    refundBusy.value = false;
  }
};

const openCancelModal = (attendee: EmsAttendee) => {
  cancelTarget.value = attendee;
  cancelReason.value = '';
  const isPaid = attendee.is_free === false || attendee.type === 'paid';
  cancelIssueRefund.value = isPaid && canRefundPayments.value;
  cancelRefundAmount.value = attendee.amount_paid && attendee.amount_paid > 0
    ? Number((attendee.amount_paid - (attendee.amount_refunded ?? 0)).toFixed(2))
    : '';
};

const closeCancelModal = () => {
  cancelTarget.value = null;
  cancelReason.value = '';
  cancelIssueRefund.value = false;
  cancelRefundAmount.value = '';
};

const executeCancellation = async () => {
  if (!cancelTarget.value || cancelReason.value.trim().length < 3) return;
  cancelBusy.value = true;
  try {
    const rawAmt = cancelRefundAmount.value;
    const refundAmt = typeof rawAmt === 'number'
      ? rawAmt
      : (typeof rawAmt === 'string' && rawAmt.trim() !== '' ? parseFloat(rawAmt) : undefined);

    await operationsService.cancelRegistration(cancelTarget.value.uuid, {
      reason: cancelReason.value.trim(),
      issue_refund: cancelIssueRefund.value,
      refund_amount: cancelIssueRefund.value && refundAmt !== undefined && !isNaN(refundAmt) ? refundAmt : undefined,
    });
    closeCancelModal();
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    cancelBusy.value = false;
  }
};

const openHistoryModal = async () => {
  isHistoryModalOpen.value = true;
  isHistoryLoading.value = true;
  historyError.value = null;
  try {
    historyList.value = await operationsService.listEventCancellationHistory(uuid.value);
  } catch (caught) {
    historyError.value = handle(caught, { silent: true }).message;
  } finally {
    isHistoryLoading.value = false;
  }
};

onMounted(load);
watch([registrationStatus, paymentStatus, checkInStatus, source, sortBy, sortDirection], () => {
  page.value = 1;
  void load();
});
</script>

<template>
  <div>
    <EmsPageHeader
      title="Attendees"
      :description="`${total} total`"
      :back-to="`/ems/events/${uuid}/operations`"
      back-label="Operations"
    >
      <template #actions>
        <Button
          v-if="canViewCancellationHistory"
          variant="outline"
          class="border-neutral-300 text-neutral-700 hover:bg-neutral-50"
          @click="openHistoryModal"
        >
          Cancellation History
        </Button>
        <Button
          variant="outline"
          class="border-emerald-600/30 text-emerald-700 hover:bg-emerald-50 font-semibold"
          @click="isManualModalOpen = true"
        >
          💵 Add Cash / Guest Invite
        </Button>
        <Button
          v-if="canImportAttendees"
          variant="outline"
          @click="router.push({ name: 'ems-event-import', params: { uuid } })"
        >
          Import
        </Button>
        <Button
          v-if="canCheckIn"
          @click="router.push({ name: 'ems-event-check-in', params: { uuid } })"
        >
          Check-in
        </Button>
      </template>
    </EmsPageHeader>

    <div class="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
      <form class="md:col-span-2 xl:col-span-2" @submit.prevent="() => { page = 1; load(); }">
        <Input v-model="search" placeholder="Search name, email, ticket, QR…" />
      </form>
      <Select v-model="registrationStatus" :options="statusOptions" label="Registration status" />
      <Select v-model="paymentStatus" :options="paymentOptions" label="Payment status" />
      <Select v-model="checkInStatus" :options="checkInOptions" label="Check-in status" />
      <Select v-model="source" :options="sourceOptions" label="Source" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <Select v-model="sortBy" :options="sortOptions" label="Sort by" />
      <Button
        variant="outline"
        size="sm"
        @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'"
      >
        {{ sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
      </Button>
      <Button variant="outline" size="sm" @click="load">Apply search</Button>
      <span class="text-xs text-neutral-muted">{{ selected.size }} selected</span>
    </div>

    <div v-if="isLoading" class="h-48 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    <EmsErrorState v-else-if="error" title="Unable to load attendees" :message="error" can-retry @retry="load" />

    <div v-else class="overflow-x-auto rounded-2xl border border-neutral-ivory bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted">
          <tr>
            <th class="px-3 py-3">
              <input type="checkbox" :checked="selected.size === attendees.length && attendees.length > 0" @change="toggleAll" />
            </th>
            <th class="px-3 py-3">Name</th>
            <th class="px-3 py-3">Email</th>
            <th class="px-3 py-3">Ticket</th>
            <th class="px-3 py-3">Registration</th>
            <th class="px-3 py-3">Payment</th>
            <th class="px-3 py-3">Attendance</th>
            <th class="px-3 py-3">Source</th>
            <th class="px-3 py-3" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in attendees" :key="row.uuid" class="border-b border-neutral-ivory/70">
            <td class="px-3 py-3">
              <input type="checkbox" :checked="selected.has(row.uuid)" @change="toggle(row.uuid)" />
            </td>
            <td class="px-3 py-3 font-medium text-neutral-black">{{ row.attendee_name }}</td>
            <td class="px-3 py-3 text-neutral-muted">{{ row.attendee_email }}</td>
            <td class="px-3 py-3">
              <div>{{ row.ticket_type?.name || '—' }}</div>
              <div v-if="row.ticket_code" class="font-mono text-xs text-neutral-muted">{{ row.ticket_code }}</div>
            </td>
            <td class="px-3 py-3">
              <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border shadow-sm', getStatusStyle(row.registration_status).bg, getStatusStyle(row.registration_status).text, getStatusStyle(row.registration_status).border]">
                <svg class="h-3.5 w-3.5 stroke-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="getStatusStyle(row.registration_status).icon" />
                </svg>
                {{ row.registration_status_label }}
              </span>
            </td>
            <td class="px-3 py-3">
              <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border shadow-sm', getStatusStyle(row.payment_status).bg, getStatusStyle(row.payment_status).text, getStatusStyle(row.payment_status).border]">
                <svg class="h-3.5 w-3.5 stroke-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="getStatusStyle(row.payment_status).icon" />
                </svg>
                {{ row.payment_status_label }}
              </span>
              <div v-if="row.refund_status && row.refund_status !== 'not_applicable' && row.refund_status !== 'not_requested'" class="mt-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                  Refund: {{ row.refund_status_label || row.refund_status }}
                </span>
              </div>
            </td>
            <td class="px-3 py-3">
              <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border shadow-sm', getStatusStyle(row.check_in_status).bg, getStatusStyle(row.check_in_status).text, getStatusStyle(row.check_in_status).border]">
                <svg class="h-3.5 w-3.5 stroke-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="getStatusStyle(row.check_in_status).icon" />
                </svg>
                {{ row.check_in_status_label }}
              </span>
              <div v-if="row.check_in_at" class="mt-1 text-xs text-neutral-muted">
                {{ new Date(row.check_in_at).toLocaleString() }}
              </div>
            </td>
            <td class="px-3 py-3 capitalize">{{ row.source_channel_label || row.registration_source.replaceAll('_', ' ') }}</td>
            <td class="px-3 py-3 text-right space-x-1 whitespace-nowrap">
              <Button
                v-if="canUndoCheckIn && row.check_in_status === 'checked_in'"
                size="sm"
                variant="ghost"
                @click="undoTarget = row"
              >
                Undo
              </Button>
              <Button
                v-if="canRefundPayments && row.payment_uuid && ['paid', 'partially_refunded'].includes(row.payment_status) && row.registration_status !== 'cancelled'"
                size="sm"
                variant="ghost"
                @click="refundTarget = row"
              >
                Refund
              </Button>
              <Button
                v-if="canCancelRegistrations && row.registration_status !== 'cancelled' && row.registration_status !== 'refunded'"
                size="sm"
                variant="ghost"
                class="text-red-600 hover:bg-red-50 hover:text-red-700"
                @click="openCancelModal(row)"
              >
                Cancel
              </Button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!attendees.length" class="p-6 text-sm text-neutral-muted">No attendees match these filters.</p>
    </div>

    <div v-if="lastPage > 1" class="mt-4 flex items-center justify-between">
      <Button variant="outline" size="sm" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
      <span class="text-xs text-neutral-muted">Page {{ page }} of {{ lastPage }}</span>
      <Button variant="outline" size="sm" :disabled="page >= lastPage" @click="page += 1; load()">Next</Button>
    </div>

    <div
      v-if="undoTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="undoTarget = null"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-soft">
        <h3 class="text-lg font-semibold">Undo check-in</h3>
        <p class="mt-1 text-sm text-neutral-muted">{{ undoTarget.attendee_name }}</p>
        <Input v-model="undoReason" class="mt-4" placeholder="Reason (required)" />
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="ghost" @click="undoTarget = null">Cancel</Button>
          <Button :disabled="!undoReason.trim()" @click="confirmUndo">Undo check-in</Button>
        </div>
      </div>
    </div>

    <div
      v-if="refundTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="refundTarget = null"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-soft">
        <h3 class="text-lg font-semibold">Refund payment</h3>
        <p class="mt-1 text-sm text-neutral-muted">{{ refundTarget.attendee_name }}</p>
        <p class="mt-2 text-xs text-amber-700">Square will process this refund. A fully refunded ticket cannot be checked in.</p>
        <Input v-model="refundReason" class="mt-4" placeholder="Reason (optional)" />
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="ghost" @click="refundTarget = null">Cancel</Button>
          <Button :disabled="refundBusy" @click="confirmRefund">{{ refundBusy ? 'Submitting…' : 'Refund' }}</Button>
        </div>
      </div>
    </div>

    <!-- Admin Cancel Registration Dialog -->
    <div
      v-if="cancelTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
      @click.self="closeCancelModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl border border-neutral-100">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
          <h3 class="text-lg font-bold text-neutral-900">Cancel Registration</h3>
          <span
            :class="[
              'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border',
              cancelTarget.is_free || cancelTarget.type === 'free'
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-amber-50 text-amber-800 border-amber-200'
            ]"
          >
            {{ cancelTarget.is_free || cancelTarget.type === 'free' ? 'Free Ticket' : 'Paid Ticket' }}
          </span>
        </div>

        <div class="mt-4 space-y-4">
          <div class="rounded-xl bg-neutral-50 p-3.5 border border-neutral-200/60 text-xs space-y-1.5">
            <div class="flex justify-between">
              <span class="text-neutral-500">Attendee:</span>
              <span class="font-semibold text-neutral-900">{{ cancelTarget.attendee_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-500">Email:</span>
              <span class="font-mono text-neutral-700">{{ cancelTarget.attendee_email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-500">Reference:</span>
              <span class="font-mono text-neutral-700">{{ cancelTarget.reference }}</span>
            </div>
            <div v-if="!cancelTarget.is_free && cancelTarget.type !== 'free'" class="flex justify-between pt-1 border-t border-neutral-200/60 font-semibold">
              <span class="text-neutral-600">Payment Amount:</span>
              <span class="text-neutral-900">
                {{ cancelTarget.currency || 'CAD' }} ${{ Number(cancelTarget.amount_paid ?? cancelTarget.amount_due ?? 0).toFixed(2) }}
              </span>
            </div>
          </div>

          <!-- Refund controls for paid tickets -->
          <div
            v-if="!cancelTarget.is_free && cancelTarget.type !== 'free'"
            class="rounded-xl border border-purple-100 bg-purple-50/50 p-4 space-y-3"
          >
            <div class="flex items-center justify-between">
              <div>
                <label class="text-xs font-bold text-neutral-900 block">Issue Refund</label>
                <p class="text-[11px] text-neutral-500">Submit a refund request to Square</p>
              </div>
              <input
                type="checkbox"
                v-model="cancelIssueRefund"
                :disabled="!canRefundPayments"
                class="h-4 w-4 rounded border-neutral-300 text-purple-600 focus:ring-purple-500 cursor-pointer disabled:opacity-50"
              />
            </div>

            <div v-if="!canRefundPayments" class="text-[11px] text-amber-700 font-medium">
              ⚠️ You do not have refund permissions (payments.refund). You may only cancel without issuing a refund.
            </div>

            <div v-if="cancelIssueRefund" class="pt-2 border-t border-purple-100">
              <label class="text-[11px] font-semibold text-neutral-700 block mb-1">
                Refund Amount (CAD)
              </label>
              <Input
                type="number"
                step="0.01"
                v-model="cancelRefundAmount"
                placeholder="Full refund amount"
              />
              <p class="text-[10px] text-neutral-400 mt-1">Leave empty or set to remaining balance for a full refund.</p>
            </div>
          </div>

          <!-- Mandatory Cancellation Reason -->
          <div>
            <label class="text-xs font-bold text-neutral-900 block mb-1">
              Cancellation Reason <span class="text-red-500">*</span>
            </label>
            <Input
              v-model="cancelReason"
              placeholder="e.g. Attendee requested cancellation via email"
              class="w-full"
            />
            <p v-if="cancelReason.trim().length > 0 && cancelReason.trim().length < 3" class="text-[11px] text-red-500 mt-1">
              Reason must be at least 3 characters.
            </p>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-3">
          <Button variant="ghost" :disabled="cancelBusy" @click="closeCancelModal">Cancel</Button>
          <Button
            variant="destructive"
            :disabled="cancelBusy || cancelReason.trim().length < 3"
            @click="executeCancellation"
          >
            {{ cancelBusy ? 'Processing…' : (cancelIssueRefund ? 'Cancel & Issue Refund' : 'Cancel Registration') }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Cancellation & Refund History Modal -->
    <div
      v-if="isHistoryModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
      @click.self="isHistoryModalOpen = false"
    >
      <div class="w-full max-w-4xl max-h-[85vh] flex flex-col rounded-2xl bg-white p-6 shadow-2xl border border-neutral-100">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
          <div>
            <h3 class="text-lg font-bold text-neutral-900">Event Cancellation & Refund History</h3>
            <p class="text-xs text-neutral-500">Audit trail of all cancelled registrations and refund transactions</p>
          </div>
          <Button variant="ghost" size="sm" @click="isHistoryModalOpen = false">Close</Button>
        </div>

        <div class="flex-1 overflow-y-auto my-4">
          <div v-if="isHistoryLoading" class="py-12 text-center text-sm text-neutral-500">
            Loading cancellation history…
          </div>
          <div v-else-if="historyError" class="py-8 text-center text-sm text-red-600">
            {{ historyError }}
          </div>
          <div v-else-if="historyList.length === 0" class="py-12 text-center text-sm text-neutral-400">
            No cancellations have been recorded for this event.
          </div>
          <table v-else class="min-w-full text-left text-xs">
            <thead class="border-b border-neutral-100 text-[10px] font-extrabold uppercase tracking-wider text-neutral-400">
              <tr>
                <th class="py-2.5 px-3">Date</th>
                <th class="py-2.5 px-3">Attendee</th>
                <th class="py-2.5 px-3">Reference</th>
                <th class="py-2.5 px-3">Initiator</th>
                <th class="py-2.5 px-3">Reason</th>
                <th class="py-2.5 px-3">Refund Status</th>
                <th class="py-2.5 px-3">Gateway Ref</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-for="item in historyList" :key="item.uuid" class="hover:bg-neutral-50/60">
                <td class="py-2.5 px-3 whitespace-nowrap text-neutral-500">
                  {{ item.cancelled_at ? new Date(item.cancelled_at).toLocaleDateString() : '—' }}
                </td>
                <td class="py-2.5 px-3 font-medium text-neutral-900">
                  {{ item.attendee_name || '—' }}
                  <div class="text-[10px] text-neutral-400 font-normal">{{ item.attendee_email }}</div>
                </td>
                <td class="py-2.5 px-3 font-mono text-neutral-600">{{ item.registration_reference || '—' }}</td>
                <td class="py-2.5 px-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-neutral-100 text-neutral-800 capitalize">
                    {{ item.initiator_type }}
                  </span>
                  <div v-if="item.initiator_name" class="text-[10px] text-neutral-400">
                    {{ item.initiator_name }}
                  </div>
                </td>
                <td class="py-2.5 px-3 max-w-xs truncate text-neutral-700" :title="item.reason">
                  {{ item.reason }}
                </td>
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold',
                      item.refund_status === 'succeeded' ? 'bg-green-50 text-green-700 border border-green-200' :
                      item.refund_status === 'failed' ? 'bg-red-50 text-red-700 border border-red-200' :
                      item.refund_status === 'pending' || item.refund_status === 'processing' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                      'bg-neutral-50 text-neutral-600 border border-neutral-200'
                    ]"
                  >
                    {{ item.refund_status_label || item.refund_status }}
                    <span v-if="item.refund_amount" class="ml-1 font-bold">
                      (${{ Number(item.refund_amount).toFixed(2) }})
                    </span>
                  </span>
                </td>
                <td class="py-2.5 px-3 font-mono text-[10px] text-neutral-400">
                  {{ item.provider_refund_id || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <ManualRegistrationModal
      :is-open="isManualModalOpen"
      :event-uuid="uuid"
      @close="isManualModalOpen = false"
      @registered="load"
    />
  </div>
</template>
