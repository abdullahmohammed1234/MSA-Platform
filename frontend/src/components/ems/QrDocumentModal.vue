<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog } from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';
import { documentsService } from '@/services/ems/documentsService';
import type { EventDocument, QrCodeResponse } from '@/types/ems';

const props = defineProps<{
  isOpen: boolean;
  eventUuid: string;
  document: EventDocument | null;
  rawToken?: string | null;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'rotated', res: QrCodeResponse): void;
}>();

const isLoading = ref(false);
const isRotating = ref(false);
const qrData = ref<QrCodeResponse | null>(null);
const errorMessage = ref<string | null>(null);
const copied = ref(false);

watch(
  [() => props.isOpen, () => props.document],
  async ([open, doc]) => {
    if (open && doc) {
      copied.value = false;
      errorMessage.value = null;
      isLoading.value = true;
      try {
        qrData.value = await documentsService.getQr(props.eventUuid, doc.uuid, props.rawToken || undefined);
      } catch (err: any) {
        errorMessage.value = err?.message || 'Failed to load QR code.';
      } finally {
        isLoading.value = false;
      }
    } else {
      qrData.value = null;
    }
  },
  { immediate: true }
);

async function handleRotate() {
  if (!props.document) return;
  if (!confirm('Are you sure you want to regenerate this access QR code? The previous printed/scanned QR link will immediately stop working.')) {
    return;
  }
  isRotating.value = true;
  errorMessage.value = null;
  try {
    const res = await documentsService.rotateAccess(props.eventUuid, props.document.uuid);
    qrData.value = {
      document: res.document,
      raw_token: res.raw_token,
      qr_url: res.qr_url,
      qr_data_uri: await (await documentsService.getQr(props.eventUuid, props.document.uuid, res.raw_token)).qr_data_uri,
    };
    emit('rotated', qrData.value);
  } catch (err: any) {
    errorMessage.value = err?.message || 'Failed to rotate access token.';
  } finally {
    isRotating.value = false;
  }
}

function handleDownloadPng() {
  if (!qrData.value?.qr_data_uri || !props.document) return;
  const link = document.createElement('a');
  link.href = qrData.value.qr_data_uri;
  const safeName = props.document.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
  link.download = `${safeName}-qr.png`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

async function copyUrl() {
  if (!qrData.value?.qr_url) return;
  try {
    await navigator.clipboard.writeText(qrData.value.qr_url);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    console.error('Failed to copy QR URL:', err);
  }
}
</script>

<template>
  <Dialog
    :is-open="isOpen"
    :title="document ? `Document QR: ${document.name}` : 'Document QR Code'"
    size="md"
    @close="$emit('close')"
  >
    <div v-if="isLoading" class="py-12 text-center text-neutral-muted">
      <div class="inline-block animate-spin w-8 h-8 border-2 border-emerald-600 border-t-transparent rounded-full mb-2"></div>
      <p class="text-sm font-medium">Generating high-resolution QR code...</p>
    </div>

    <div v-else-if="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium">
      {{ errorMessage }}
    </div>

    <div v-else-if="qrData" class="flex flex-col items-center gap-4 py-2">
      <!-- High-res QR Display -->
      <div class="p-4 bg-white rounded-2xl shadow-sm border border-neutral-200">
        <img
          :src="qrData.qr_data_uri"
          :alt="`QR code for ${document?.name}`"
          class="w-64 h-64 object-contain"
        />
      </div>

      <div class="text-center space-y-1">
        <h4 class="text-base font-bold text-neutral-black">{{ document?.name }}</h4>
        <p class="text-xs text-neutral-muted">Scan to view document inline on any mobile device</p>
      </div>

      <!-- Access URL snippet -->
      <div class="w-full bg-neutral-50 border border-neutral-ivory rounded-xl p-3 flex items-center justify-between gap-2">
        <span class="text-xs font-mono text-neutral-black truncate select-all">
          {{ qrData.qr_url }}
        </span>
        <button
          type="button"
          @click="copyUrl"
          class="px-2.5 py-1 bg-white hover:bg-neutral-100 border border-neutral-ivory text-neutral-black text-xs rounded-md transition-colors font-medium shrink-0"
        >
          {{ copied ? 'Copied!' : 'Copy Link' }}
        </button>
      </div>

      <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed text-left">
        <strong>Security Notice:</strong> This QR URL contains an access token bearer credential. Anyone with this QR code can view the attached PDF. Rotate access token if printed material or link is leaked.
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <Button
          variant="outline"
          size="sm"
          :is-loading="isRotating"
          @click="handleRotate"
          class="text-amber-800 border-amber-300 hover:bg-amber-50"
        >
          Regenerate QR / Rotate Token
        </Button>
        <div class="flex items-center gap-2">
          <Button variant="ghost" @click="$emit('close')">Close</Button>
          <Button variant="primary" :disabled="!qrData" @click="handleDownloadPng">
            Download PNG
          </Button>
        </div>
      </div>
    </template>
  </Dialog>
</template>
