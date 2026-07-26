<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Camera, Keyboard } from 'lucide-vue-next';

const props = defineProps<{
  busy?: boolean;
}>();

const emit = defineEmits<{
  scan: [code: string];
}>();

const scannerId = `ems-qr-${Math.random().toString(36).slice(2, 9)}`;
const scanner = ref<Html5Qrcode | null>(null);
const cameraError = ref<string | null>(null);
const isRunning = ref(false);
const manualCode = ref('');
const lastScanAt = ref(0);
const lastCode = ref('');

const startCamera = async () => {
  cameraError.value = null;
  try {
    const instance = new Html5Qrcode(scannerId);
    scanner.value = instance;
    await instance.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      (decoded) => {
        const now = Date.now();
        if (decoded === lastCode.value && now - lastScanAt.value < 2000) {
          return;
        }
        lastCode.value = decoded;
        lastScanAt.value = now;
        if (!props.busy) {
          emit('scan', decoded);
        }
      },
      () => undefined
    );
    isRunning.value = true;
  } catch (error) {
    isRunning.value = false;
    const message = (error as Error)?.message || 'Unable to access the camera.';
    cameraError.value =
      message.includes('Permission') || message.includes('NotAllowed')
        ? 'Camera permission denied. Allow camera access, or use HTTPS (required on most phones). You can still enter a ticket code manually.'
        : `${message} You can still enter a ticket code manually.`;
  }
};

const stopCamera = async () => {
  if (scanner.value && isRunning.value) {
    try {
      await scanner.value.stop();
      await scanner.value.clear();
    } catch {
      // ignore
    }
  }
  isRunning.value = false;
  scanner.value = null;
};

const submitManual = () => {
  const code = manualCode.value.trim();
  if (!code || props.busy) return;
  emit('scan', code);
  manualCode.value = '';
};

onMounted(startCamera);
onBeforeUnmount(stopCamera);
watch(
  () => props.busy,
  () => undefined
);
</script>

<template>
  <div class="space-y-4">
    <div
      :id="scannerId"
      class="overflow-hidden rounded-2xl border border-neutral-ivory bg-neutral-black/90 min-h-[240px]"
    />

    <p v-if="cameraError" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      {{ cameraError }}
    </p>

    <div class="flex items-center gap-2 text-xs text-neutral-muted">
      <Camera class="h-3.5 w-3.5" />
      <span>{{ isRunning ? 'Camera scanning…' : 'Camera idle' }}</span>
    </div>

    <form class="flex gap-2" @submit.prevent="submitManual">
      <div class="relative flex-1">
        <Keyboard class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-muted" />
        <Input
          v-model="manualCode"
          class="pl-9"
          placeholder="Or enter ticket code"
          autocomplete="off"
          :disabled="busy"
        />
      </div>
      <Button type="submit" :disabled="busy || !manualCode.trim()">Check in</Button>
    </form>
  </div>
</template>
