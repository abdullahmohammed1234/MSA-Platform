<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import { Camera, CameraOff, RefreshCw } from 'lucide-vue-next';

const emit = defineEmits<{
  (e: 'scan', barcode: string): void;
  (e: 'error', errMessage: string): void;
}>();

const scannerContainerId = 'mlibms-camera-scanner-' + Math.random().toString(36).substring(2, 9);
const isScanning = ref(false);
const errorMessage = ref('');
let html5QrcodeScanner: Html5Qrcode | null = null;

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

const startScanner = async () => {
  errorMessage.value = '';
  try {
    if (!html5QrcodeScanner) {
      html5QrcodeScanner = new Html5Qrcode(scannerContainerId);
    }

    isScanning.value = true;

    await html5QrcodeScanner.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        qrbox: { width: 250, height: 180 },
        aspectRatio: 1.333333,
      },
      (decodedText) => {
        playBeep();
        emit('scan', decodedText.trim());
      },
      (_err) => {
        // Ignore frame decode errors during active scanning
      }
    );
  } catch (err: any) {
    isScanning.value = false;
    errorMessage.value = err?.message || 'Camera access denied or no camera device available.';
    emit('error', errorMessage.value);
  }
};

const stopScanner = async () => {
  if (html5QrcodeScanner && isScanning.value) {
    try {
      await html5QrcodeScanner.stop();
    } catch (e) {
      console.warn('Error stopping camera scanner:', e);
    } finally {
      isScanning.value = false;
    }
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
        <span>{{ isScanning ? 'Stop Camera' : 'Start Camera' }}</span>
      </button>
    </div>

    <!-- Scanner Viewfinder Container -->
    <div class="relative rounded-xl overflow-hidden bg-neutral-black/90 min-h-[220px] flex items-center justify-center">
      <div :id="scannerContainerId" class="w-full h-full"></div>

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
