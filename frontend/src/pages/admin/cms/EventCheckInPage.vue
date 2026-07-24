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
} from 'lucide-vue-next';
import cmsService from '@/services/cms/cmsService';

const route = useRoute();

const scannerHostId = 'event-checkin-scanner';
const scanner = ref<Html5Qrcode | null>(null);
const cameraActive = ref(false);
const cameraError = ref('');
const isProcessing = ref(false);
const manualCode = ref('');
const lastResult = ref<{
  success: boolean;
  message: string;
  alreadyCheckedIn?: boolean;
  name?: string;
  status?: string;
} | null>(null);
const recentScans = ref<Array<{ code: string; message: string; at: string; success: boolean }>>([]);

const selectedEventUuid = computed(() => {
  const value = route.query.event;
  return typeof value === 'string' && value.length > 0 ? value : undefined;
});

async function startCamera() {
  cameraError.value = '';
  lastResult.value = null;

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
    };

    recentScans.value.unshift({
      code,
      message: result.message,
      at: new Date().toLocaleTimeString(),
      success: result.success,
    });
    recentScans.value = recentScans.value.slice(0, 8);
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
    };

    recentScans.value.unshift({
      code,
      message,
      at: new Date().toLocaleTimeString(),
      success: false,
    });
    recentScans.value = recentScans.value.slice(0, 8);
  } finally {
    isProcessing.value = false;
  }
}

async function submitManualCode() {
  if (!manualCode.value.trim()) return;
  await handleScan(manualCode.value);
  manualCode.value = '';
}

onMounted(() => {
  startCamera();
});

onUnmounted(() => {
  stopCamera().finally(() => {
    scanner.value?.clear();
    scanner.value = null;
  });
});

watch(selectedEventUuid, () => {
  lastResult.value = null;
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-extrabold text-primary">Event Check-In</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Scan a registrant QR code to mark them as attending.
          <span v-if="selectedEventUuid" class="block mt-1 text-xs">
            Filtering to event: {{ selectedEventUuid }}
          </span>
        </p>
      </div>
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
              <p v-if="lastResult.status" class="text-xs uppercase tracking-widest font-bold mt-3 opacity-70">
                Status: {{ lastResult.status }}
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
          <h3 class="text-sm font-bold text-neutral-black mb-4">Recent scans</h3>
          <div v-if="recentScans.length === 0" class="text-sm text-neutral-muted">
            No scans yet this session.
          </div>
          <ul v-else class="space-y-3">
            <li
              v-for="(scan, index) in recentScans"
              :key="`${scan.at}-${index}`"
              class="rounded-2xl border border-neutral-ivory px-4 py-3"
            >
              <div class="flex items-center justify-between gap-3">
                <span
                  class="text-[10px] font-extrabold uppercase tracking-widest"
                  :class="scan.success ? 'text-emerald-700' : 'text-red-700'"
                >
                  {{ scan.success ? 'OK' : 'Failed' }}
                </span>
                <span class="text-[10px] text-neutral-muted">{{ scan.at }}</span>
              </div>
              <p class="text-sm text-neutral-black mt-1">{{ scan.message }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
