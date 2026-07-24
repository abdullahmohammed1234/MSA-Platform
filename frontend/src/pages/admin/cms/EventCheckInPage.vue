<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import {
  Camera,
  CheckCircle2,
  Keyboard,
  QrCode,
  RefreshCw,
  AlertTriangle,
  Users,
} from 'lucide-vue-next';
import cmsService from '@/services/cms/cmsService';

type CheckInRecord = {
  uuid: string;
  full_name: string;
  email: string;
  phone: string | null;
  status: string;
  checked_in_at: string | null;
  event: { uuid: string | null; title: string | null };
};

const route = useRoute();

const scannerHostId = 'event-checkin-scanner';
const scanner = ref<Html5Qrcode | null>(null);
const cameraActive = ref(false);
const cameraError = ref('');
const isProcessing = ref(false);
const isLoadingCheckIns = ref(false);
const manualCode = ref('');
const lastResult = ref<{
  success: boolean;
  message: string;
  alreadyCheckedIn?: boolean;
  name?: string;
  status?: string;
  email?: string;
  checkedInAt?: string | null;
  eventTitle?: string | null;
} | null>(null);
const attendingList = ref<CheckInRecord[]>([]);
const listError = ref('');

const selectedEventUuid = computed(() => {
  const value = route.query.event;
  return typeof value === 'string' && value.length > 0 ? value : undefined;
});

function formatCheckInTime(value?: string | null) {
  if (!value) return 'Saved';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}

async function loadAttendingList() {
  isLoadingCheckIns.value = true;
  listError.value = '';
  try {
    attendingList.value = await cmsService.getRecentEventCheckIns({
      eventUuid: selectedEventUuid.value,
      limit: 50,
    });
  } catch (err: any) {
    listError.value =
      err?.response?.data?.message ||
      err?.message ||
      'Could not load saved check-ins from the database.';
  } finally {
    isLoadingCheckIns.value = false;
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

    const result = await cmsService.checkInEventRegistration(code, selectedEventUuid.value);
    lastResult.value = {
      success: result.success,
      message: result.message,
      alreadyCheckedIn: result.alreadyCheckedIn,
      name: result.registration?.full_name,
      status: result.registration?.status,
      email: result.registration?.email,
      checkedInAt: result.registration?.checked_in_at,
      eventTitle: result.registration?.event?.title,
    };

    // Always refresh from DB so attending status survives page reloads.
    await loadAttendingList();
  } catch (err: any) {
    const message =
      err?.response?.data?.message ||
      err?.message ||
      'Check-in failed. Please try again.';

    lastResult.value = {
      success: false,
      message,
      name: err?.response?.data?.registration?.full_name,
      status: err?.response?.data?.registration?.status,
      email: err?.response?.data?.registration?.email,
      checkedInAt: err?.response?.data?.registration?.checked_in_at,
      eventTitle: err?.response?.data?.registration?.event?.title,
    };
  } finally {
    isProcessing.value = false;
  }
}

async function submitManualCode() {
  if (!manualCode.value.trim()) return;
  await handleScan(manualCode.value);
  manualCode.value = '';
}

onMounted(async () => {
  await loadAttendingList();
  await startCamera();
});

onUnmounted(() => {
  stopCamera().finally(() => {
    scanner.value?.clear();
    scanner.value = null;
  });
});

watch(selectedEventUuid, async () => {
  lastResult.value = null;
  await loadAttendingList();
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-extrabold text-primary">Event Check-In</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Scan a registrant QR code to mark them as attending in the database.
          <span v-if="selectedEventUuid" class="block mt-1 text-xs">
            Filtering to event: {{ selectedEventUuid }}
          </span>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white text-primary px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-primary/5 cursor-pointer"
          :disabled="isLoadingCheckIns"
          @click="loadAttendingList"
        >
          <RefreshCw :size="14" />
          Refresh list
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
              placeholder="Paste QR payload or registration UUID"
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
              <p v-if="lastResult.eventTitle" class="text-xs text-neutral-muted mt-1">{{ lastResult.eventTitle }}</p>
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
          <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-bold text-neutral-black inline-flex items-center gap-2">
              <Users :size="14" /> Attending (saved)
            </h3>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-primary">
              {{ attendingList.length }}
            </span>
          </div>

          <p v-if="listError" class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-4 py-3 mb-3">
            {{ listError }}
          </p>
          <p v-else-if="isLoadingCheckIns" class="text-sm text-neutral-muted">
            Loading saved check-ins...
          </p>
          <div v-else-if="attendingList.length === 0" class="text-sm text-neutral-muted">
            No attending guests saved yet. Successful scans are stored in the database and will appear here after refresh.
          </div>
          <ul v-else class="space-y-3 max-h-[28rem] overflow-y-auto">
            <li
              v-for="person in attendingList"
              :key="person.uuid"
              class="rounded-2xl border border-emerald-100 bg-emerald-50/40 px-4 py-3"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">
                  Attending
                </span>
                <span class="text-[10px] text-neutral-muted">{{ formatCheckInTime(person.checked_in_at) }}</span>
              </div>
              <p class="text-sm font-semibold text-neutral-black mt-1">{{ person.full_name }}</p>
              <p class="text-xs text-neutral-muted">{{ person.email }}</p>
              <p v-if="person.event?.title" class="text-xs text-neutral-muted mt-1">{{ person.event.title }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
