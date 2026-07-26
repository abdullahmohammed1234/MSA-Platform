<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { EmsPageHeader } from '@/components/ems';
import QrScannerPanel from '@/components/ems/operations/QrScannerPanel.vue';
import CheckInResultBanner from '@/components/ems/operations/CheckInResultBanner.vue';
import ManualCheckInPanel from '@/components/ems/operations/ManualCheckInPanel.vue';
import WalkInPanel from '@/components/ems/operations/WalkInPanel.vue';
import { operationsService, checkInErrorPayload } from '@/services/ems/operationsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import type { EmsCheckInResult } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const { handle } = useEmsApiError();
const { canCreateRegistrations } = useEmsPermissions();

const uuid = computed(() => route.params.uuid as string);
const result = ref<EmsCheckInResult | null>(null);
const busy = ref(false);
const tab = ref<'scan' | 'search' | 'walkin'>('scan');

const onScan = async (code: string) => {
  if (busy.value) return;
  busy.value = true;
  try {
    const response = await operationsService.checkIn(uuid.value, {
      code,
      method: 'qr_scan',
      device: navigator.userAgent.slice(0, 60),
    });
    result.value = response;
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
</script>

<template>
  <div>
    <EmsPageHeader
      title="Check-in"
      description="Scan QR codes or search attendees"
      :back-to="`/ems/events/${uuid}/operations`"
      back-label="Operations"
    >
      <template #actions>
        <Button variant="outline" @click="router.push({ name: 'ems-event-staff', params: { uuid } })">
          Staff mode
        </Button>
      </template>
    </EmsPageHeader>

    <div class="mb-4 flex flex-wrap gap-2">
      <Button :variant="tab === 'scan' ? 'primary' : 'outline'" size="sm" @click="tab = 'scan'">QR scanner</Button>
      <Button :variant="tab === 'search' ? 'primary' : 'outline'" size="sm" @click="tab = 'search'">Search</Button>
      <Button
        v-if="canCreateRegistrations"
        :variant="tab === 'walkin' ? 'primary' : 'outline'"
        size="sm"
        @click="tab = 'walkin'"
      >
        Walk-in
      </Button>
    </div>

    <CheckInResultBanner class="mb-4" :result="result" />

    <div class="grid gap-6 lg:grid-cols-2">
      <section v-if="tab === 'scan'" class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
        <QrScannerPanel :busy="busy" @scan="onScan" />
      </section>
      <section v-else-if="tab === 'search'" class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
        <ManualCheckInPanel :event-uuid="uuid" @result="result = $event" />
      </section>
      <section v-else>
        <WalkInPanel :event-uuid="uuid" />
      </section>
    </div>
  </div>
</template>
