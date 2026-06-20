<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Camera, ChevronLeft, ChevronRight, Share2, X } from 'lucide-vue-next';
import type { LightboxImage } from '@/types/lightbox';
import { downloadImage, shareImage } from '@/utils/imageActions';

const props = withDefaults(
  defineProps<{
    images: LightboxImage[];
    modelValue: number | null;
    showSidebar?: boolean;
  }>(),
  {
    showSidebar: true,
  },
);

const emit = defineEmits<{
  'update:modelValue': [index: number | null];
  close: [];
}>();

const isDownloading = ref(false);
const shareStatus = ref<'idle' | 'shared' | 'copied' | 'failed'>('idle');

const currentIndex = computed(() => props.modelValue ?? 0);
const currentImage = computed(() => props.images[currentIndex.value] ?? null);
const hasMultiple = computed(() => props.images.length > 1);

const close = () => {
  emit('update:modelValue', null);
  emit('close');
};

const navigate = (direction: 'prev' | 'next') => {
  if (!hasMultiple.value || props.modelValue === null) return;

  const total = props.images.length;
  const nextIndex =
    direction === 'next'
      ? (currentIndex.value + 1) % total
      : (currentIndex.value - 1 + total) % total;

  emit('update:modelValue', nextIndex);
  shareStatus.value = 'idle';
};

const handleDownload = async () => {
  if (!currentImage.value || isDownloading.value) return;

  isDownloading.value = true;
  try {
    await downloadImage(
      currentImage.value.url,
      currentImage.value.downloadFilename,
    );
  } finally {
    isDownloading.value = false;
  }
};

const handleShare = async () => {
  if (!currentImage.value) return;

  const result = await shareImage({
    url: currentImage.value.url,
    title: currentImage.value.title ?? 'SFU MSA',
    text: currentImage.value.subtitle ?? currentImage.value.description,
  });

  shareStatus.value = result;
  if (result !== 'failed') {
    setTimeout(() => {
      shareStatus.value = 'idle';
    }, 2500);
  }
};

const onKeydown = (event: KeyboardEvent) => {
  if (props.modelValue === null) return;

  if (event.key === 'Escape') {
    close();
  } else if (event.key === 'ArrowLeft') {
    navigate('prev');
  } else if (event.key === 'ArrowRight') {
    navigate('next');
  }
};

watch(
  () => props.modelValue,
  (index) => {
    document.body.style.overflow = index !== null ? 'hidden' : '';
    shareStatus.value = 'idle';
  },
  { immediate: true },
);

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue !== null && currentImage"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-black/95 p-4 md:p-20 backdrop-blur-2xl"
      role="dialog"
      aria-modal="true"
      :aria-label="currentImage.title ?? 'Image viewer'"
      @click.self="close"
    >
      <button
        type="button"
        class="absolute top-6 right-6 md:top-10 md:right-10 text-neutral-white/50 hover:text-secondary-light transition-colors z-[110]"
        aria-label="Close"
        @click="close"
      >
        <X class="w-10 h-10" />
      </button>

      <div
        class="w-full h-full max-w-7xl flex flex-col lg:flex-row gap-8 lg:gap-12 items-center"
        @click.stop
      >
        <div class="flex-1 w-full h-full relative flex items-center justify-center min-h-0">
          <img
            :src="currentImage.url"
            :alt="currentImage.title ?? 'Gallery image'"
            class="max-w-full max-h-[70vh] lg:max-h-full object-contain rounded-3xl shadow-premium shadow-primary/10"
            referrerpolicy="no-referrer"
          />

          <div
            v-if="hasMultiple"
            class="absolute left-0 right-0 flex justify-between pointer-events-none px-2 md:px-4"
          >
            <button
              type="button"
              class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-neutral-white/5 border border-neutral-white/10 text-neutral-white flex items-center justify-center hover:bg-secondary-light hover:text-neutral-white transition-all pointer-events-auto"
              aria-label="Previous image"
              @click="navigate('prev')"
            >
              <ChevronLeft class="w-6 h-6" />
            </button>
            <button
              type="button"
              class="w-12 h-12 md:w-14 md:h-14 rounded-full bg-neutral-white/5 border border-neutral-white/10 text-neutral-white flex items-center justify-center hover:bg-secondary-light hover:text-neutral-white transition-all pointer-events-auto"
              aria-label="Next image"
              @click="navigate('next')"
            >
              <ChevronRight class="w-6 h-6" />
            </button>
          </div>

          <div
            v-if="hasMultiple"
            class="absolute bottom-2 left-1/2 -translate-x-1/2 text-neutral-white/50 text-[10px] font-black uppercase tracking-widest"
          >
            {{ currentIndex + 1 }} / {{ images.length }}
          </div>
        </div>

        <div
          v-if="showSidebar"
          class="w-full lg:w-96 shrink-0 space-y-6 lg:space-y-8"
        >
          <div class="space-y-4">
            <span
              v-if="currentImage.category"
              class="px-4 py-1 bg-secondary-light/10 border border-secondary-light/20 rounded-full text-secondary-light text-[10px] font-black uppercase tracking-widest leading-none font-sans"
            >
              {{ currentImage.category }}
            </span>
            <h2
              v-if="currentImage.title"
              class="text-3xl lg:text-4xl font-display font-medium text-neutral-white"
            >
              {{ currentImage.title }}
            </h2>
            <p
              v-if="currentImage.subtitle"
              class="text-neutral-white/70 font-light"
            >
              {{ currentImage.subtitle }}
            </p>
            <p
              v-if="currentImage.description"
              class="text-neutral-white/60 font-light leading-relaxed"
            >
              {{ currentImage.description }}
            </p>
          </div>

          <div class="h-[1px] bg-neutral-white/10" />

          <div
            v-if="currentImage.date"
            class="flex items-center gap-2 text-neutral-white/40 text-[10px] font-black uppercase tracking-widest font-sans"
          >
            <Camera class="w-4 h-4" />
            <span>Captured on {{ currentImage.date }}</span>
          </div>

          <div class="flex gap-4">
            <button
              type="button"
              class="flex-1 py-4 bg-neutral-white/5 border border-neutral-white/10 rounded-2xl text-neutral-white hover:bg-secondary-light transition-all font-bold uppercase tracking-widest text-[10px] disabled:opacity-50"
              :disabled="isDownloading"
              @click="handleDownload"
            >
              {{ isDownloading ? 'Downloading…' : 'Download Original' }}
            </button>
            <button
              type="button"
              class="w-14 h-14 bg-secondary-light rounded-2xl flex items-center justify-center text-neutral-white hover:scale-105 transition-all shadow-glow shadow-secondary-light/20"
              :aria-label="shareStatus === 'copied' ? 'Link copied' : 'Share image'"
              @click="handleShare"
            >
              <Share2 class="w-5 h-5" />
            </button>
          </div>

          <p
            v-if="shareStatus === 'copied'"
            class="text-[10px] font-bold uppercase tracking-widest text-accent-gold"
          >
            Link copied to clipboard
          </p>
          <p
            v-else-if="shareStatus === 'shared'"
            class="text-[10px] font-bold uppercase tracking-widest text-accent-gold"
          >
            Shared successfully
          </p>
          <p
            v-else-if="shareStatus === 'failed'"
            class="text-[10px] font-bold uppercase tracking-widest text-red-300"
          >
            Could not share this image
          </p>
        </div>

        <div
          v-else
          class="flex gap-4 w-full lg:w-auto justify-center"
        >
          <button
            type="button"
            class="px-6 py-4 bg-neutral-white/5 border border-neutral-white/10 rounded-2xl text-neutral-white hover:bg-secondary-light transition-all font-bold uppercase tracking-widest text-[10px] disabled:opacity-50"
            :disabled="isDownloading"
            @click="handleDownload"
          >
            {{ isDownloading ? 'Downloading…' : 'Download' }}
          </button>
          <button
            type="button"
            class="w-14 h-14 bg-secondary-light rounded-2xl flex items-center justify-center text-neutral-white hover:scale-105 transition-all"
            aria-label="Share image"
            @click="handleShare"
          >
            <Share2 class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
