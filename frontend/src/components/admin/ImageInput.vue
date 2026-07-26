<script setup lang="ts">
import { ref, computed } from 'vue';
import { UploadCloud, Link2, X, Loader2 } from 'lucide-vue-next';
import cmsService from '@/services/cms/cmsService';
import { resolvePublicImagePath, toStorableImagePath } from '@/constants/publicAssets';

const props = withDefaults(defineProps<{
  modelValue: string | null | undefined;
  label?: string;
  placeholder?: string;
  hint?: string;
  /** Tailwind classes applied to the preview image element. */
  previewClass?: string;
  /** Accepted file types for the device upload picker. */
  accept?: string;
  /** External validation error to display. */
  error?: string;
}>(), {
  modelValue: '',
  label: '',
  placeholder: 'Paste an image link (https://...) or /Hero/photo.webp',
  hint: '',
  previewClass: 'w-full max-h-40 object-cover',
  accept: 'image/*',
  error: '',
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const isDragging = ref(false);
const uploadError = ref<string | null>(null);

const previewSrc = computed(() =>
  props.modelValue ? resolvePublicImagePath(props.modelValue) : ''
);

const setValue = (value: string) => {
  emit('update:modelValue', value);
};

const triggerFileSelect = () => {
  uploadError.value = null;
  fileInput.value?.click();
};

const uploadFile = async (file: File) => {
  if (!file.type.startsWith('image/')) {
    uploadError.value = 'Please choose an image file (JPEG, PNG, WEBP, or GIF).';
    return;
  }

  isUploading.value = true;
  uploadError.value = null;
  try {
    const media = await cmsService.uploadMedia(file);
    setValue(toStorableImagePath(media.url) || media.url);
  } catch (err: any) {
    uploadError.value = err?.response?.data?.message || 'Failed to upload image. Please try again.';
  } finally {
    isUploading.value = false;
  }
};

const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';
  if (file) {
    await uploadFile(file);
  }
};

const handleDrop = async (event: DragEvent) => {
  isDragging.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    await uploadFile(file);
  }
};

const clearImage = () => {
  uploadError.value = null;
  setValue('');
};
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" class="text-[10px] font-black uppercase tracking-widest text-primary/70">
      {{ label }}
    </label>

    <input
      type="file"
      ref="fileInput"
      class="hidden"
      :accept="accept"
      @change="handleFileChange"
    />

    <!-- Upload dropzone / button -->
    <div
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileSelect"
      :class="[
        'flex items-center justify-center gap-2 w-full border border-dashed rounded-2xl py-4 px-5 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer',
        isDragging
          ? 'border-primary bg-primary/5 text-primary'
          : 'border-neutral-gray/30 bg-neutral-background text-primary hover:border-primary/40 hover:bg-primary/5',
        isUploading ? 'opacity-60 pointer-events-none' : ''
      ]"
    >
      <Loader2 v-if="isUploading" :size="16" class="animate-spin" />
      <UploadCloud v-else :size="16" />
      {{ isUploading ? 'Uploading...' : 'Upload from device' }}
    </div>

    <!-- Divider -->
    <div class="flex items-center gap-3 py-0.5">
      <div class="h-px flex-grow bg-neutral-gray/15"></div>
      <span class="text-[9px] font-black uppercase tracking-widest text-neutral-black/30">or paste a link</span>
      <div class="h-px flex-grow bg-neutral-gray/15"></div>
    </div>

    <!-- URL text input -->
    <div class="relative">
      <Link2 class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="14" />
      <input
        type="text"
        :value="modelValue ?? ''"
        @input="setValue(($event.target as HTMLInputElement).value)"
        :placeholder="placeholder"
        class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 pl-11 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
      />
    </div>

    <p v-if="hint" class="text-[10px] text-neutral-black/40 leading-relaxed">{{ hint }}</p>

    <p v-if="error || uploadError" class="text-[10px] font-bold text-secondary">{{ error || uploadError }}</p>

    <!-- Preview -->
    <div v-if="modelValue" class="relative pt-2 w-fit max-w-full">
      <img
        :src="previewSrc"
        alt="Image preview"
        :class="['rounded-2xl border border-neutral-gray/20 bg-neutral-background', previewClass]"
      />
      <button
        type="button"
        @click="clearImage"
        title="Remove image"
        class="absolute top-3 right-1 p-1.5 bg-white/90 border border-neutral-gray/20 text-secondary rounded-full shadow-sm hover:bg-secondary hover:text-white transition-all cursor-pointer"
      >
        <X :size="12" />
      </button>
    </div>
  </div>
</template>
