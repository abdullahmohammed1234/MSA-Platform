<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { Camera, CameraOff, RefreshCw, CheckCircle2 } from 'lucide-vue-next';
import { buildIsbnScannerQrbox, isBookIsbn13, sanitizeScannedBarcode } from '@/utils/isbn';

const props = withDefaults(defineProps<{
  cooldownMs?: number;
}>(), {
  cooldownMs: 3000,
});

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
const isLocked = ref(false);
const showSearchingHint = ref(false);

let html5QrcodeScanner: Html5Qrcode | null = null;
let lastScannedText = '';
let lastScanTime = 0;
let feedbackTimer: ReturnType<typeof setTimeout> | null = null;
let searchingHintTimer: ReturnType<typeof setTimeout> | null = null;

// Frame consensus tracking to prevent frame decode jitter
let candidateBarcode = '';
let candidateCount = 0;
let lastCandidateTime = 0;

// Controlled decode diagnostics (throttled; no per-frame spam)
let decodeMissCount = 0;
let lastDiagLogAt = 0;

const clearFeedbackTimer = () => {
  if (feedbackTimer) {
    clearTimeout(feedbackTimer);
    feedbackTimer = null;
  }
};

const clearSearchingHintTimer = () => {
  if (searchingHintTimer) {
    clearTimeout(searchingHintTimer);
    searchingHintTimer = null;
  }
};

const resetDecodeDiagnostics = () => {
  decodeMissCount = 0;
  showSearchingHint.value = false;
  clearSearchingHintTimer();
};

const playBeep = () => {
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.12, ctx.currentTime);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
  } catch (e) {
    // AudioContext fallback
  }
};

const onScanSuccessCallback = (decodedText: string) => {
  const cleaned = sanitizeScannedBarcode(decodedText);
  if (!cleaned || cleaned.length < 4) return;

  const now = Date.now();

  if (cleaned === lastScannedText && now - lastScanTime < props.cooldownMs) {
    return;
  }

  // Frame consensus stabilization: require 2 consecutive frames for candidate match
  if (cleaned === candidateBarcode && now - lastCandidateTime < 600) {
    candidateCount++;
  } else {
    candidateBarcode = cleaned;
    candidateCount = 1;
    lastCandidateTime = now;
  }

  const isFormedIsbn = isBookIsbn13(cleaned);
  const isFormedCopyBarcode = cleaned.toUpperCase().startsWith('MLIB-');

  if (candidateCount < 2 && !isFormedIsbn && !isFormedCopyBarcode) {
    return;
  }

  resetDecodeDiagnostics();

  lastScannedText = cleaned;
  lastScanTime = now;
  lastDetectedBarcode.value = cleaned;
  showDetectionFeedback.value = true;
  isLocked.value = true;

  candidateBarcode = '';
  candidateCount = 0;

  clearFeedbackTimer();
  feedbackTimer = setTimeout(() => {
    showDetectionFeedback.value = false;
    isLocked.value = false;
    feedbackTimer = null;
  }, 2500);

  playBeep();
  // Dual emit kept for API compatibility; intake listens only to scan-success.
  emit('scan', cleaned);
  emit('scan-success', cleaned);
};

const onScanFailureCallback = (_errorMessage: string) => {
  decodeMissCount += 1;

  if (decodeMissCount === 1 && !searchingHintTimer) {
    searchingHintTimer = setTimeout(() => {
      if (isScanning.value && !lastDetectedBarcode.value) {
        showSearchingHint.value = true;
      }
      searchingHintTimer = null;
    }, 8000);
  }

  const now = Date.now();
  // Throttle console diagnostics (~every 75 misses or ≥12s)
  if (decodeMissCount === 75 || (decodeMissCount > 0 && now - lastDiagLogAt >= 12000 && decodeMissCount % 25 === 0)) {
    lastDiagLogAt = now;
    console.info('[MLibMS Scanner] Still attempting barcode decode', {
      misses: decodeMissCount,
      hint: 'Hold the ISBN barcode steady inside the wide scan strip.',
    });
  }
};

const stopScanner = async () => {
  clearSearchingHintTimer();
  showSearchingHint.value = false;

  if (!html5QrcodeScanner) {
    isScanning.value = false;
    isLocked.value = false;
    return;
  }

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
    isLocked.value = false;
  }
};

const startScanner = async () => {
  errorMessage.value = '';
  lastScannedText = '';
  candidateBarcode = '';
  candidateCount = 0;
  resetDecodeDiagnostics();
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
    await stopScanner();

    // Top-level useBarCodeDetectorIfSupported is preferred in html5-qrcode 2.3.8.
    // experimentalFeatures kept in sync so native BarcodeDetector is primary when
    // supported, with automatic ZXing fallback inside the library.
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
      useBarCodeDetectorIfSupported: true,
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
        qrbox: buildIsbnScannerQrbox,
        aspectRatio: 1.777778,
        videoConstraints: {
          facingMode: 'environment',
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
      },
      onScanSuccessCallback,
      onScanFailureCallback,
    );
  } catch (err: any) {
    isScanning.value = false;
    errorMessage.value = err?.message || 'Camera access denied or no camera device available.';
    emit('error', errorMessage.value);
  }
};

const resetScanState = () => {
  lastScannedText = '';
  candidateBarcode = '';
  candidateCount = 0;
  showDetectionFeedback.value = false;
  isLocked.value = false;
  resetDecodeDiagnostics();
};

onMounted(() => {
  startScanner();
});

onUnmounted(() => {
  clearFeedbackTimer();
  clearSearchingHintTimer();
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

      <div class="flex items-center gap-2">
        <button
          v-if="lastDetectedBarcode"
          type="button"
          @click="resetScanState"
          class="px-2.5 py-1.5 rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background text-[11px] font-bold text-neutral-black transition-all cursor-pointer"
        >
          Reset Scan
        </button>

        <button
          type="button"
          @click="isScanning ? stopScanner() : startScanner()"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-neutral-ivory bg-neutral-background hover:bg-neutral-ivory/40 text-xs font-bold text-neutral-black transition-all cursor-pointer"
        >
          <component :is="isScanning ? CameraOff : RefreshCw" class="w-3.5 h-3.5 text-primary" />
          <span>{{ isScanning ? 'Pause Camera' : 'Start Camera' }}</span>
        </button>
      </div>
    </div>

    <!-- Scanner Viewfinder Container -->
    <div class="relative rounded-xl overflow-hidden bg-neutral-black/90 min-h-[220px] flex items-center justify-center">
      <div :id="scannerContainerId" class="w-full h-full"></div>

      <!-- Scanned Detection Notification -->
      <div
        v-if="showDetectionFeedback"
        class="absolute top-3 left-3 right-3 bg-emerald-600/90 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between backdrop-blur-md shadow-lg transition-all animate-fade-in z-20"
      >
        <div class="flex items-center gap-2 truncate">
          <CheckCircle2 class="w-4 h-4 text-emerald-200 shrink-0" />
          <span class="truncate">Locked: <span class="font-mono underline font-bold">{{ lastDetectedBarcode }}</span></span>
        </div>
        <span class="text-[10px] bg-emerald-700/80 px-2 py-0.5 rounded-md font-mono">Confirmed</span>
      </div>

      <div
        v-else-if="showSearchingHint && isScanning && !errorMessage"
        class="absolute bottom-3 left-3 right-3 bg-neutral-black/80 text-white/90 px-3 py-2 rounded-xl text-[11px] font-medium z-20"
      >
        Still looking for an ISBN barcode. Center the horizontal bars in the scan strip, hold steady, and reduce glare.
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
