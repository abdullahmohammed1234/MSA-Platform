<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import {
  Camera,
  CheckCircle2,
  Keyboard,
  QrCode,
  RefreshCw,
  AlertTriangle,
  Users,
  List,
} from 'lucide-vue-next';
import cmsService from '@/services/cms/cmsService';
import type { Event, EventRegistration } from '@/types/cms';

const route = useRoute();
const router = useRouter();

const scannerHostId = 'event-checkin-scanner';
const scanner = ref<Html5Qrcode | null>(null);
const cameraActive = ref(false);
const cameraError = ref('');
const isProcessing = ref(false);
const isLoadingList = ref(false);
const manualCode = ref('');
const events = ref<Event[]>([]);
const selectedEventUuid = ref<string>('');
const registrants = ref<EventRegistration[]>([]);
const highlightedUuid = ref<string | null>(null);
const listError = ref('');
const lastResult = ref<{
  success: boolean;
  message: string;
  alreadyCheckedIn?: boolean;
  name?: string;
  status?: string;
  email?: string;
} | null>(null);

const selectedEvent = computed(() =>
  events.value.find((event) => event.uuid === selectedEventUuid.value) || null
);

const attendingCount = computed(
  () => registrants.value.filter((person) => person.status === 'attending').length
);

function parseEventUuidFromCode(raw: string): string | null {
  const value = raw.trim();
  const prefixed = value.startsWith('sfumsa:event-checkin:')
    ? value.slice('sfumsa:event-checkin:'.length)
    : value;
  const match = prefixed.match(
    /^([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}):([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$/i
  );
  return match ? match[1].toLowerCase() : null;
}

async function loadEvents() {
  const res = await cmsService.getEvents({ per_page: 100, status: 'published' });
  events.value = res.data;

  const queryEvent = typeof route.query.event === 'string' ? route.query.event : '';
  if (queryEvent && events.value.some((event) => event.uuid === queryEvent)) {
    selectedEventUuid.value = queryEvent;
  } else if (!selectedEventUuid.value && events.value[0]) {
    selectedEventUuid.value = events.value[0].uuid;
  }
}

async function loadRegistrants() {
  if (!selectedEventUuid.value) {
    registrants.value = [];
    return;
  }

  isLoadingList.value = true;
  listError.value = '';

  try {
    const res = await cmsService.getEventRegistrations(selectedEventUuid.value, {
      per_page: 200,
    });
    registrants.value = res.data;
  } catch (err: any) {
    listError.value =
      err?.response?.data?.message ||
      err?.message ||
      'Could not load this event\'s registration list.';
  } finally {
    isLoadingList.value = false;
  }
}

async function startCamera() {
  cameraError.value = '';
  await nextTick();

  try {
    if (!scanner.value) {
      scanner.value = new Html5Qrcode(scannerHostId, {
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        verbose: false,
      });
    }

    await scanner.value.start(
      { facingMode: 'environment' },
      {
        fps: 8,
        qrbox: { width: 260, height: 260 },
        aspectRatio: 1,
      },
      async (decodedText) => {
        await handleScan(decodedText);
      },
      () => undefined
    );

    cameraActive.value = true;
  } catch (err: any) {
    cameraActive.value = false;
    cameraError.value =
      err?.message ||
      'Unable to access the camera. Allow camera permissions or enter the code manually.';
  }
}

async function stopCamera() {
  if (!scanner.value) return;

  try {
    if (scanner.value.isScanning) {
      await scanner.value.stop();
    }
  } catch {
    // ignore stop errors during teardown
  }

  cameraActive.value = false;
}

async function handleScan(rawCode: string) {
  const code = rawCode.trim();
  if (!code || isProcessing.value) return;

  isProcessing.value = true;

  try {
    if (cameraActive.value) {
      await stopCamera();
    }

    // 1) Read event id from QR first and open that event's registrant list.
    const eventFromQr = parseEventUuidFromCode(code);
    if (eventFromQr && eventFromQr !== selectedEventUuid.value) {
      selectedEventUuid.value = eventFromQr;
      await loadRegistrants();
      router.replace({
        name: 'admin-cms-event-check-in',
        query: { event: eventFromQr },
      });
    }

    // 2) Match hidden check-in code against that event's registrations and mark attending.
    const result = await cmsService.checkInEventRegistration(
      code,
      selectedEventUuid.value || eventFromQr || undefined
    );

    if (result.eventUuid && result.eventUuid !== selectedEventUuid.value) {
      selectedEventUuid.value = result.eventUuid;
    }

    lastResult.value = {
      success: result.success,
      message: result.message,
      alreadyCheckedIn: result.alreadyCheckedIn,
      name: result.registration?.full_name,
      status: result.registration?.status,
      email: result.registration?.email,
    };

    highlightedUuid.value = result.registration?.uuid || null;
    await loadRegistrants();
  } catch (err: any) {
    const message =
      err?.response?.data?.message ||
      err?.message ||
      'Check-in failed. Please try again.';

    if (err?.response?.data?.eventUuid) {
      selectedEventUuid.value = err.response.data.eventUuid;
      await loadRegistrants();
    }

    lastResult.value = {
      success: false,
      message,
      name: err?.response?.data?.registration?.full_name,
      status: err?.response?.data?.registration?.status,
      email: err?.response?.data?.registration?.email,
    };
    highlightedUuid.value = err?.response?.data?.registration?.uuid || null;
  } finally {
    isProcessing.value = false;
  }
}

async function submitManualCode() {
  if (!manualCode.value.trim()) return;
  await handleScan(manualCode.value);
  manualCode.value = '';
}

function openFullList() {
  if (!selectedEventUuid.value) return;
  router.push({
    name: 'admin-cms-event-registrations',
    params: { uuid: selectedEventUuid.value },
  });
}

onMounted(async () => {
  try {
    await loadEvents();
    await loadRegistrants();
  } catch (err: any) {
    listError.value = err?.message || 'Failed to load events.';
  }
  await startCamera();
});

onUnmounted(() => {
  stopCamera().finally(() => {
    scanner.value?.clear();
    scanner.value = null;
  });
});

watch(selectedEventUuid, async (uuid) => {
  highlightedUuid.value = null;
  lastResult.value = null;
  await loadRegistrants();
  if (uuid) {
    router.replace({
      name: 'admin-cms-event-check-in',
      query: { event: uuid },
    });
  }
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div class="space-y-3 flex-1">
        <h1 class="text-2xl font-display font-extrabold text-primary">Event Check-In</h1>
        <p class="text-sm text-neutral-muted">
          QR codes include the event ID. Scanning opens that event’s registrant list, matches the hidden check-in code, and marks the guest as attending.
        </p>
        <div class="max-w-xl">
          <label class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-muted block mb-2">
            Active event list
          </label>
          <select
            v-model="selectedEventUuid"
            class="w-full rounded-2xl border border-neutral-ivory bg-white px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
          >
            <option disabled value="">Select an event</option>
            <option v-for="event in events" :key="event.uuid" :value="event.uuid">
              {{ event.title }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white text-primary px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-primary/5 cursor-pointer"
          :disabled="!selectedEventUuid"
          @click="openFullList"
        >
          <List :size="14" /> Full list
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white text-primary px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-primary/5 cursor-pointer"
          :disabled="isLoadingList"
          @click="loadRegistrants"
        >
          <RefreshCw :size="14" /> Refresh
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-primary-dark cursor-pointer"
          :disabled="isProcessing"
          @click="cameraActive ? stopCamera() : startCamera()"
        >
          <Camera :size="14" />
          {{ cameraActive ? 'Stop camera' : 'Start camera' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3 space-y-4">
        <div class="bg-white border border-neutral-ivory rounded-3xl p-4 sm:p-6 shadow-soft">
          <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary mb-4">
            <QrCode :size="14" /> Camera scanner
          </div>
          <div
            :id="scannerHostId"
            class="overflow-hidden rounded-2xl bg-neutral-black/90 min-h-[280px] w-full"
          />
          <p v-if="cameraError" class="mt-4 text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
            {{ cameraError }}
          </p>
          <p v-else-if="!cameraActive" class="mt-4 text-sm text-neutral-muted">
            Camera is stopped. Start it to scan the next guest.
          </p>
        </div>

        <div class="bg-white border border-neutral-ivory rounded-3xl p-4 sm:p-6 shadow-soft">
          <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary mb-4">
            <Keyboard :size="14" /> Manual entry
          </div>
          <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="submitManualCode">
            <input
              v-model="manualCode"
              type="text"
              placeholder="Paste QR payload"
              class="flex-1 rounded-xl border border-neutral-ivory px-4 py-3 text-sm focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
            />
            <button
              type="submit"
              class="rounded-xl bg-secondary text-white px-5 py-3 text-xs font-bold uppercase tracking-wider cursor-pointer disabled:opacity-60"
              :disabled="isProcessing || !manualCode.trim()"
            >
              Check in
            </button>
          </form>
        </div>
      </div>

      <div class="xl:col-span-2 space-y-4">
        <div
          v-if="lastResult"
          class="rounded-3xl border p-6"
          :class="lastResult.success ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'"
        >
          <div class="flex items-start gap-3">
            <CheckCircle2 v-if="lastResult.success" :size="22" class="text-emerald-700 shrink-0" />
            <AlertTriangle v-else :size="22" class="text-red-700 shrink-0" />
            <div>
              <h2 class="font-semibold text-neutral-black">
                {{ lastResult.name || (lastResult.success ? 'Checked in' : 'Scan failed') }}
              </h2>
              <p class="text-sm mt-1" :class="lastResult.success ? 'text-emerald-900' : 'text-red-900'">
                {{ lastResult.message }}
              </p>
              <p v-if="lastResult.email" class="text-xs text-neutral-muted mt-2">{{ lastResult.email }}</p>
              <p v-if="lastResult.status" class="text-xs uppercase tracking-widest font-bold mt-3 opacity-70">
                Status: {{ lastResult.status }}
                <span v-if="lastResult.alreadyCheckedIn"> (already scanned)</span>
              </p>
            </div>
          </div>
          <button
            v-if="!cameraActive"
            type="button"
            class="mt-5 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary cursor-pointer"
            @click="startCamera"
          >
            <RefreshCw :size="14" /> Scan next
          </button>
        </div>

        <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft">
          <div class="flex items-center justify-between gap-3 mb-2">
            <h3 class="text-sm font-bold text-neutral-black inline-flex items-center gap-2">
              <Users :size="14" />
              {{ selectedEvent?.title || 'Event registrants' }}
            </h3>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-primary">
              {{ attendingCount }}/{{ registrants.length }} attending
            </span>
          </div>
          <p class="text-[11px] text-neutral-muted mb-4">
            Names and status only — check-in codes stay hidden and are matched in the background.
          </p>

          <p v-if="listError" class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-4 py-3 mb-3">
            {{ listError }}
          </p>
          <p v-else-if="isLoadingList" class="text-sm text-neutral-muted">Loading registrants...</p>
          <div v-else-if="registrants.length === 0" class="text-sm text-neutral-muted">
            No one is registered for this event yet.
          </div>
          <ul v-else class="space-y-2 max-h-[28rem] overflow-y-auto">
            <li
              v-for="person in registrants"
              :key="person.uuid"
              class="rounded-2xl border px-4 py-3 transition-colors"
              :class="highlightedUuid === person.uuid
                ? 'border-accent-gold bg-accent-gold/10'
                : person.status === 'attending'
                  ? 'border-emerald-100 bg-emerald-50/50'
                  : 'border-neutral-ivory'"
            >
              <div class="flex items-center justify-between gap-3">
                <span
                  class="text-[10px] font-extrabold uppercase tracking-widest"
                  :class="person.status === 'attending' ? 'text-emerald-700' : 'text-primary'"
                >
                  {{ person.status }}
                </span>
              </div>
              <p class="text-sm font-semibold text-neutral-black mt-1">{{ person.full_name }}</p>
              <p class="text-xs text-neutral-muted">{{ person.email }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
