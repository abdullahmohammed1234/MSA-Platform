<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { EmsErrorState, EmsPageHeader, EmsSummaryCard } from '@/components/ems';
import { operationsService } from '@/services/ems/operationsService';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import type { EmsOperationsSummary } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const events = useEmsEventsStore();
const { canCheckIn, canImportAttendees, canViewAttendees, canUpdateEvents, isEventStaffOnly } =
  useEmsPermissions();
const { handle } = useEmsApiError();

const uuid = computed(() => route.params.uuid as string);
const summary = ref<EmsOperationsSummary | null>(null);
const error = ref<string | null>(null);
const isLoading = ref(true);
let timer: ReturnType<typeof setInterval> | null = null;

const money = (amount: number, currency = 'CAD') =>
  new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount);

const load = async () => {
  try {
    error.value = null;
    if (!events.current || events.current.uuid !== uuid.value) {
      await events.fetchOne(uuid.value);
    }
    summary.value = await operationsService.getSummary(uuid.value);
  } catch (caught) {
    const err = handle(caught, { silent: true });
    error.value = err.message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await load();
  timer = setInterval(load, 10000);
});
onUnmounted(() => {
  if (timer) clearInterval(timer);
});
watch(uuid, load);
</script>

<template>
  <div>
    <EmsPageHeader
      title="Event operations"
      :description="summary?.event_name || events.current?.name"
      :back-to="`/ems/events/${uuid}`"
      back-label="Event detail"
    >
      <template #actions>
        <Button
          v-if="canViewAttendees"
          variant="outline"
          @click="router.push({ name: 'ems-event-attendees', params: { uuid } })"
        >
          Attendees
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
          @click="router.push({ name: isEventStaffOnly ? 'ems-event-staff' : 'ems-event-check-in', params: { uuid } })"
        >
          {{ isEventStaffOnly ? 'Staff mode' : 'Check-in' }}
        </Button>
      </template>
    </EmsPageHeader>

    <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    </div>

    <EmsErrorState
      v-else-if="error"
      title="Unable to load operations"
      :message="error"
      can-retry
      @retry="load"
    />

    <template v-else-if="summary">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <EmsSummaryCard label="Registered" :value="summary.registered_count" />
        <EmsSummaryCard label="Checked in" :value="summary.checked_in_count" />
        <EmsSummaryCard
          label="Remaining"
          :value="summary.remaining_count ?? 0"
        />
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Attendance</p>
          <p class="mt-2 text-3xl font-bold tabular-nums text-primary">{{ summary.attendance_percentage }}%</p>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Capacity</p>
          <p class="mt-2 text-3xl font-bold tabular-nums text-primary">
            {{ summary.capacity === null ? '∞' : summary.capacity }}
          </p>
        </div>
        <EmsSummaryCard label="Waitlist" :value="summary.waitlist_count" />
        <EmsSummaryCard label="Walk-ins" :value="summary.walk_in_count" />
        <EmsSummaryCard label="Confirmed" :value="summary.confirmed_count" />
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
          <h2 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">
            Registration status
          </h2>
          <ul class="mt-3 space-y-2 text-sm">
            <li
              v-for="(count, status) in summary.registration_status_summary"
              :key="status"
              class="flex justify-between"
            >
              <span class="capitalize text-neutral-muted">{{ String(status).replaceAll('_', ' ') }}</span>
              <span class="font-medium text-neutral-black">{{ count }}</span>
            </li>
          </ul>
        </section>

        <section
          v-if="summary.payment_summary && canUpdateEvents"
          class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"
        >
          <h2 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">
            Payment summary
          </h2>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-neutral-muted">Paid</dt>
              <dd>{{ money(summary.payment_summary.paid_amount, summary.payment_summary.currency) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-neutral-muted">Pending</dt>
              <dd>{{ money(summary.payment_summary.pending_amount, summary.payment_summary.currency) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-neutral-muted">Refunded</dt>
              <dd>{{ money(summary.payment_summary.refunded_amount, summary.payment_summary.currency) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-neutral-muted">Failed</dt>
              <dd>{{ summary.payment_summary.failed_count }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <section class="mt-6 rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <h2 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">
          Recent check-ins
        </h2>
        <ul v-if="summary.recent_check_ins.length" class="mt-3 divide-y divide-neutral-ivory">
          <li
            v-for="item in summary.recent_check_ins"
            :key="item.uuid"
            class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
          >
            <div>
              <p class="font-medium text-neutral-black">{{ item.attendee_name || 'Guest' }}</p>
              <p class="text-xs text-neutral-muted">
                {{ item.method_label }}
                <span v-if="item.ticket_code" class="font-mono"> · {{ item.ticket_code }}</span>
              </p>
            </div>
            <div class="text-right text-xs text-neutral-muted">
              <p>{{ item.checked_in_at ? new Date(item.checked_in_at).toLocaleTimeString() : '—' }}</p>
              <p v-if="item.staff_name">{{ item.staff_name }}</p>
            </div>
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-neutral-muted">No check-ins yet.</p>
      </section>
    </template>
  </div>
</template>
