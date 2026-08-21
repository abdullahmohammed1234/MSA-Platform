<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { operationsService } from '@/services/ems/operationsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EmsAttendee } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const { handle } = useEmsApiError();
const { canCheckIn, canImportAttendees, canUndoCheckIn, canRefundPayments } = useEmsPermissions();
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
            <td class="px-3 py-3 text-right">
              <Button
                v-if="canUndoCheckIn && row.check_in_status === 'checked_in'"
                size="sm"
                variant="ghost"
                @click="undoTarget = row"
              >
                Undo
              </Button>
              <Button
                v-if="canRefundPayments && row.payment_uuid && ['paid', 'partially_refunded'].includes(row.payment_status)"
                size="sm"
                variant="ghost"
                @click="refundTarget = row"
              >
                Refund
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
  </div>
</template>
