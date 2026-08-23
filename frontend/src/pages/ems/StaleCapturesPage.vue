<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { AlertTriangle, Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { staleCapturesService } from '@/services/ems/staleCapturesService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import type { StaleCapture, StaleCaptureResolutionStatus } from '@/types/ems/staleCaptures';

const router = useRouter();
const { handle } = useEmsApiError();

const items = ref<StaleCapture[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);
const search = ref('');
const resolution = ref<StaleCaptureResolutionStatus | 'all'>('unresolved');

const resolutionOptions = [
  { value: 'all', label: 'All statuses' },
  { value: 'unresolved', label: 'Unresolved' },
  { value: 'refunded', label: 'Refunded' },
  { value: 'partially_refunded', label: 'Partially refunded' },
  { value: 'resolved_no_refund', label: 'Resolved (no refund)' },
];

const load = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const result = await staleCapturesService.list({
      search: search.value.trim() || undefined,
      resolution: resolution.value,
    });
    items.value = result.items;
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(load);

const statusLabel = (status: StaleCaptureResolutionStatus): string => {
  const map: Record<StaleCaptureResolutionStatus, string> = {
    unresolved: 'Unresolved',
    refunded: 'Refunded',
    partially_refunded: 'Partially refunded',
    resolved_no_refund: 'Resolved (no refund)',
  };
  return map[status] ?? status;
};

const statusClass = (status: StaleCaptureResolutionStatus): string => {
  if (status === 'unresolved') return 'bg-amber-100 text-amber-900';
  if (status === 'refunded') return 'bg-emerald-100 text-emerald-900';
  if (status === 'partially_refunded') return 'bg-sky-100 text-sky-900';
  return 'bg-neutral-200 text-neutral-700';
};

const formatMoney = (amount: number, currency: string) =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);

const formatDate = (value: string | null) => {
  if (!value) return '—';
  return new Date(value).toLocaleString();
};

const openDetail = (row: StaleCapture) => {
  void router.push({
    name: 'ems-stale-capture-detail',
    params: { paymentUuid: row.payment_uuid, squarePaymentId: row.square_payment_id },
  });
};

const emptyMessage = computed(() =>
  resolution.value === 'unresolved'
    ? 'No unresolved stale captures require action.'
    : 'No stale captures match your filters.'
);
</script>

<template>
  <div>
    <EmsPageHeader
      title="Stale Captures"
      description="Square payments captured after an attendee cancelled checkout. EMS bookings stay cancelled — resolve each capture explicitly."
    />

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <div class="min-w-[220px] flex-1">
        <label class="mb-1 block text-xs font-semibold text-neutral-muted">Search</label>
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-muted" />
          <Input v-model="search" class="pl-9" placeholder="Attendee, order, Square payment ID…" @keyup.enter="load" />
        </div>
      </div>
      <div class="min-w-[180px]">
        <Select v-model="resolution" :options="resolutionOptions" label="Resolution" />
      </div>
      <Button variant="outline" size="sm" @click="load">Apply</Button>
    </div>

    <EmsErrorState v-if="error" :message="error" @retry="load" />

    <div v-else-if="isLoading" class="rounded-xl border border-neutral-ivory bg-white p-8 text-center text-sm text-neutral-muted">
      Loading stale captures…
    </div>

    <div
      v-else-if="items.length === 0"
      class="rounded-xl border border-dashed border-neutral-ivory bg-white p-10 text-center"
    >
      <AlertTriangle class="mx-auto h-8 w-8 text-amber-600" aria-hidden="true" />
      <p class="mt-3 text-sm font-semibold text-neutral-black">{{ emptyMessage }}</p>
    </div>

    <div v-else class="overflow-hidden rounded-xl border border-neutral-ivory bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-neutral-ivory bg-neutral-background text-xs uppercase tracking-wide text-neutral-muted">
            <tr>
              <th class="px-4 py-3 font-semibold">Attendee</th>
              <th class="px-4 py-3 font-semibold">Event</th>
              <th class="px-4 py-3 font-semibold">Amount</th>
              <th class="px-4 py-3 font-semibold">Square payment</th>
              <th class="px-4 py-3 font-semibold">Reported</th>
              <th class="px-4 py-3 font-semibold">Source</th>
              <th class="px-4 py-3 font-semibold">Status</th>
              <th class="px-4 py-3 font-semibold">Resolution</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in items"
              :key="`${row.payment_uuid}:${row.square_payment_id}`"
              class="cursor-pointer border-b border-neutral-ivory last:border-0 hover:bg-neutral-background/70"
              @click="openDetail(row)"
            >
              <td class="px-4 py-3">
                <p class="font-semibold text-neutral-black">{{ row.attendee_name ?? 'Unknown' }}</p>
                <p class="text-xs text-neutral-muted">{{ row.attendee_email }}</p>
                <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                  Stale capture — buyer cancelled
                </p>
              </td>
              <td class="px-4 py-3">
                <p>{{ row.event_name ?? 'Event unavailable' }}</p>
                <p v-if="row.event_missing" class="text-xs text-amber-700">Event deleted</p>
              </td>
              <td class="px-4 py-3 font-semibold">{{ formatMoney(row.checkout_amount, row.currency) }}</td>
              <td class="px-4 py-3 font-mono text-xs">{{ row.square_payment_id }}</td>
              <td class="px-4 py-3 text-xs">{{ formatDate(row.reported_at) }}</td>
              <td class="px-4 py-3 text-xs">{{ row.source ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-700">
                  {{ row.payment_status_label }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="statusClass(row.resolution_status)"
                >
                  {{ statusLabel(row.resolution_status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
