<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { Camera, CameraOff, RefreshCw, CheckCircle2 } from 'lucide-vue-next';

const emit = defineEmits<{
  (e: 'scan', barcode: string): void;
  (e: 'scan-success', barcode: string): void;
  (e: 'error', errMessage: string): void;
}>();

const scannerContainerId = 'mlibms-camera-scanner-' + Math.random().toString(36).substring(2, 9);
const isScanning = ref(false);
const errorMessage = ref('');
const lastDetectedBarcode = ref('');
const showDetectionFeedback = ref(false);

let html5QrcodeScanner: Html5Qrcode | null = null;
let lastScannedText = '';
let lastScanTime = 0;
let feedbackTimer: any = null;
const cooldownMs = 2000;

const playBeep = () => {
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.1, ctx.currentTime);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
  } catch (e) {
    // AudioContext fallback
  }
};

const onScanSuccessCallback = (decodedText: string) => {
  const now = Date.now();
  const cleaned = decodedText.trim();
  if (!cleaned) return;

  if (cleaned === lastScannedText && now - lastScanTime < cooldownMs) {
    return; // Skip duplicate frame within cooldown window
  }

  lastScannedText = cleaned;
  lastScanTime = now;
  lastDetectedBarcode.value = cleaned;
  showDetectionFeedback.value = true;

  clearTimeout(feedbackTimer);
  feedbackTimer = setTimeout(() => {
    showDetectionFeedback.value = false;
  }, 2000);

  playBeep();
  emit('scan', cleaned);
  emit('scan-success', cleaned);
};

const stopScanner = async () => {
  if (!html5QrcodeScanner) return;
  try {
    if (html5QrcodeScanner.isScanning) {
      await html5QrcodeScanner.stop();
    }
    await html5QrcodeScanner.clear();
  } catch (e) {
    console.warn('Error clearing camera scanner:', e);
  } finally {
    html5QrcodeScanner = null;
    isScanning.value = false;
  }
};

const startScanner = async () => {
  errorMessage.value = '';
  await nextTick();

  if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
    errorMessage.value = 'Camera scanning requires a secure HTTPS connection or localhost.';
    emit('error', errorMessage.value);
    return;
  }

  const containerEl = document.getElementById(scannerContainerId);
  if (!containerEl) {
    errorMessage.value = 'Scanner viewport element not ready.';
    return;
  }

  try {
    await stopScanner(); // Stop and clear any lingering instance

    html5QrcodeScanner = new Html5Qrcode(scannerContainerId, {
      formatsToSupport: [
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.ITF,
        Html5QrcodeSupportedFormats.QR_CODE,
      ],
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true,
      },
      verbose: false,
    });

    isScanning.value = true;

    await html5QrcodeScanner.start(
      { facingMode: 'environment' },
      {
        fps: 15,
        qrbox: (viewfinderWidth: number, viewfinderHeight: number) => ({
          width: Math.max(240, Math.floor(viewfinderWidth * 0.85)),
          height: Math.max(140, Math.floor(viewfinderHeight * 0.55)),
        }),
        aspectRatio: 1.777778,
        videoConstraints: {
          facingMode: 'environment',
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
      },
      onScanSuccessCallback,
      (_err) => {
        // Ignore frame decode misses
      }
    );
  } catch (err: any) {
    isScanning.value = false;
    errorMessage.value = err?.message || 'Camera access denied or no camera device available.';
    emit('error', errorMessage.value);
  }
};

onMounted(() => {
  startScanner();
});

onUnmounted(() => {
  stopScanner();
});
</script>

<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-4 shadow-soft space-y-3">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <Camera class="w-4 h-4 text-primary" />
        <span class="text-xs font-extrabold uppercase tracking-wider text-neutral-black">Camera Barcode Scanner</span>
      </div>

      <button
        type="button"
        @click="isScanning ? stopScanner() : startScanner()"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-neutral-ivory bg-neutral-background hover:bg-neutral-ivory/40 text-xs font-bold text-neutral-black transition-all cursor-pointer"
      >
        <component :is="isScanning ? CameraOff : RefreshCw" class="w-3.5 h-3.5 text-primary" />
        <span>{{ isScanning ? 'Pause Camera' : 'Start Camera' }}</span>
      </button>
    </div>

    <!-- Scanner Viewfinder Container -->
    <div class="relative rounded-xl overflow-hidden bg-neutral-black/90 min-h-[220px] flex items-center justify-center">
      <div :id="scannerContainerId" class="w-full h-full"></div>

      <!-- Scanned Detection Notification -->
      <div
        v-if="showDetectionFeedback"
        class="absolute top-3 left-3 right-3 bg-emerald-600/90 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between backdrop-blur-md shadow-lg transition-all animate-fade-in"
      >
        <div class="flex items-center gap-2 truncate">
          <CheckCircle2 class="w-4 h-4 text-emerald-200 shrink-0" />
          <span class="truncate">Scanned: <span class="font-mono underline">{{ lastDetectedBarcode }}</span></span>
        </div>
      </div>

      <div v-if="!isScanning && !errorMessage" class="text-center p-6 text-white/70 space-y-2">
        <CameraOff class="w-8 h-8 mx-auto text-white/50" />
        <p class="text-xs">Camera paused. Click "Start Camera" to enable live barcode scanning.</p>
      </div>

      <div v-if="errorMessage" class="text-center p-6 text-red-300 space-y-2">
        <CameraOff class="w-8 h-8 mx-auto text-red-400" />
        <p class="text-xs font-medium">{{ errorMessage }}</p>
      </div>
    </div>
  </div>
</template>
