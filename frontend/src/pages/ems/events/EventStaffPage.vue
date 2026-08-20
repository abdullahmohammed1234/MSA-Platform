<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { EmsPageHeader, EmsSummaryCard } from '@/components/ems';
import QrScannerPanel from '@/components/ems/operations/QrScannerPanel.vue';
import CheckInResultBanner from '@/components/ems/operations/CheckInResultBanner.vue';
import ManualCheckInPanel from '@/components/ems/operations/ManualCheckInPanel.vue';
import WalkInPanel from '@/components/ems/operations/WalkInPanel.vue';
import { operationsService, checkInErrorPayload } from '@/services/ems/operationsService';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import type { EmsCheckInResult, EmsOperationsSummary } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const events = useEmsEventsStore();
const { handle } = useEmsApiError();
const { formatDateRange, formatTimeRange } = useEventFormatting();

const uuid = computed(() => route.params.uuid as string);
const event = computed(() => events.current);
const summary = ref<EmsOperationsSummary | null>(null);
const result = ref<EmsCheckInResult | null>(null);
const busy = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const refresh = async () => {
  try {
    if (!event.value || event.value.uuid !== uuid.value) {
      await events.fetchOne(uuid.value);
    }
    summary.value = await operationsService.getSummary(uuid.value);
  } catch (error) {
    handle(error, { silent: true });
  }
};

const onScan = async (code: string) => {
  if (busy.value) return;
  busy.value = true;
  try {
    result.value = await operationsService.checkIn(uuid.value, {
      code,
      method: 'qr_scan',
      device: 'staff-mode',
    });
    await refresh();
  } catch (error) {
    result.value = checkInErrorPayload(error) ?? {
      ok: false,
      code: 'error',
      message: handle(error, { silent: true }).message,
    };
  } finally {
    busy.value = false;
  }
};

onMounted(async () => {
  await refresh();
  timer = setInterval(refresh, 8000);
});
onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <div>
    <EmsPageHeader
      title="Event staff"
      :description="event?.name"
      :back-to="`/ems/events/${uuid}`"
      back-label="Exit staff mode"
    >
      <template #actions>
        <Button variant="outline" @click="router.push({ name: 'ems-event-detail', params: { uuid } })">
          Exit
        </Button>
      </template>
    </EmsPageHeader>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
      <EmsSummaryCard label="Checked in" :value="summary?.checked_in_count ?? 0" />
      <EmsSummaryCard label="Registered" :value="summary?.registered_count ?? 0" />
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Attendance</p>
        <p class="mt-2 text-3xl font-bold tabular-nums text-primary">{{ summary?.attendance_percentage ?? 0 }}%</p>
      </div>
    </div>

    <section
      v-if="event"
      class="mb-4 rounded-2xl border border-neutral-ivory bg-white p-4 text-sm shadow-soft"
    >
      <h2 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Event information</h2>
      <dl class="mt-2 grid gap-2 sm:grid-cols-2">
        <div>
          <dt class="text-neutral-muted">When</dt>
          <dd>{{ formatDateRange(event.start_at, event.end_at) }} · {{ formatTimeRange(event.start_at, event.end_at) }}</dd>
        </div>
        <div>
          <dt class="text-neutral-muted">Where</dt>
          <dd>{{ event.location || '—' }}</dd>
        </div>
        <div>
          <dt class="text-neutral-muted">Capacity</dt>
          <dd>{{ event.capacity ?? 'Unlimited' }}</dd>
        </div>
      </dl>
    </section>

    <CheckInResultBanner class="mb-4" :result="result" />

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
        <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-neutral-muted">QR scanner</h2>
        <QrScannerPanel :busy="busy" @scan="onScan" />
      </section>
      <div class="space-y-6">
        <section class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
          <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Search & manual check-in</h2>
          <ManualCheckInPanel
            :event-uuid="uuid"
            @result="(r) => { result = r; refresh(); }"
          />
        </section>
        <WalkInPanel :event-uuid="uuid" @done="refresh" />
      </div>
    </div>
  </div>
</template>
