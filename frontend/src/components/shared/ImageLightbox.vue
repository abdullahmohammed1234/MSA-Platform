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

const headerLabel = computed(() => {
  if (!currentImage.value) return 'Image viewer';
  if (hasMultiple.value) {
    return `${currentIndex.value + 1} of ${props.images.length}`;
  }
  return currentImage.value.title ?? 'Image viewer';
});

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
      class="fixed inset-0 z-[100] flex flex-col bg-neutral-black/95 backdrop-blur-2xl"
      role="dialog"
      aria-modal="true"
      :aria-label="currentImage.title ?? 'Image viewer'"
    >
      <div
        class="absolute inset-0"
        aria-hidden="true"
        @click="close"
      />

      <header class="relative z-[110] flex items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4 border-b border-neutral-white/10 shrink-0">
        <p class="min-w-0 truncate text-neutral-white/70 text-xs sm:text-sm font-bold uppercase tracking-widest">
          {{ headerLabel }}
        </p>

        <button
          type="button"
          class="inline-flex items-center gap-2 shrink-0 rounded-full bg-neutral-white/10 border border-neutral-white/15 px-3 py-2 sm:px-4 text-neutral-white hover:bg-neutral-white/20 transition-colors"
          aria-label="Close image viewer"
          @click="close"
        >
          <X class="w-5 h-5" />
          <span class="text-[10px] font-bold uppercase tracking-widest">Close</span>
        </button>
      </header>

      <div class="relative z-[105] flex-1 min-h-0 overflow-y-auto overscroll-contain">
        <div
          class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8 flex flex-col lg:flex-row lg:items-start gap-6 lg:gap-10"
          @click.stop
        >
          <div class="w-full lg:flex-1 lg:min-w-0 flex flex-col items-center">
            <div class="relative w-full flex items-center justify-center rounded-2xl bg-neutral-white/5 border border-neutral-white/10 p-3 sm:p-4">
              <img
                :src="currentImage.url"
                :alt="currentImage.title ?? 'Gallery image'"
                class="max-w-full max-h-[42vh] sm:max-h-[50vh] lg:max-h-[calc(100vh-14rem)] w-auto object-contain rounded-xl"
                referrerpolicy="no-referrer"
              />

              <div
                v-if="hasMultiple"
                class="absolute inset-y-0 left-0 right-0 flex items-center justify-between px-1 sm:px-2 pointer-events-none"
              >
                <button
                  type="button"
                  class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-neutral-black/60 border border-neutral-white/20 text-neutral-white flex items-center justify-center hover:bg-secondary-light transition-all pointer-events-auto"
                  aria-label="Previous image"
                  @click="navigate('prev')"
                >
                  <ChevronLeft class="w-5 h-5 sm:w-6 sm:h-6" />
                </button>
                <button
                  type="button"
                  class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-neutral-black/60 border border-neutral-white/20 text-neutral-white flex items-center justify-center hover:bg-secondary-light transition-all pointer-events-auto"
                  aria-label="Next image"
                  @click="navigate('next')"
                >
                  <ChevronRight class="w-5 h-5 sm:w-6 sm:h-6" />
                </button>
              </div>
            </div>
          </div>

          <aside
            v-if="showSidebar"
            class="w-full lg:w-80 xl:w-96 shrink-0 space-y-5 sm:space-y-6 pb-6 lg:pb-0 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto lg:overscroll-contain"
          >
            <div class="space-y-3 sm:space-y-4">
              <span
                v-if="currentImage.category"
                class="inline-flex px-3 py-1 bg-secondary-light/10 border border-secondary-light/20 rounded-full text-secondary-light text-[10px] font-black uppercase tracking-widest leading-none font-sans"
              >
                {{ currentImage.category }}
              </span>
              <h2
                v-if="currentImage.title"
                class="text-2xl sm:text-3xl font-display font-medium text-neutral-white leading-tight"
              >
                {{ currentImage.title }}
              </h2>
              <p
                v-if="currentImage.subtitle"
                class="text-neutral-white/75 font-light text-sm sm:text-base"
              >
                {{ currentImage.subtitle }}
              </p>
              <p
                v-if="currentImage.description"
                class="text-neutral-white/60 font-light leading-relaxed text-sm sm:text-base"
              >
                {{ currentImage.description }}
              </p>
            </div>

            <div class="h-px bg-neutral-white/10" />

            <div
              v-if="currentImage.date"
              class="flex items-center gap-2 text-neutral-white/40 text-[10px] font-black uppercase tracking-widest font-sans"
            >
              <Camera class="w-4 h-4 shrink-0" />
              <span>Captured on {{ currentImage.date }}</span>
            </div>

            <div class="flex gap-3 sm:gap-4">
              <button
                type="button"
                class="flex-1 py-3.5 sm:py-4 bg-neutral-white/5 border border-neutral-white/10 rounded-2xl text-neutral-white hover:bg-secondary-light transition-all font-bold uppercase tracking-widest text-[10px] disabled:opacity-50"
                :disabled="isDownloading"
                @click="handleDownload"
              >
                {{ isDownloading ? 'Downloading…' : 'Download Original' }}
              </button>
              <button
                type="button"
                class="w-12 h-12 sm:w-14 sm:h-14 shrink-0 bg-secondary-light rounded-2xl flex items-center justify-center text-neutral-white hover:scale-105 transition-all shadow-glow shadow-secondary-light/20"
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
          </aside>

          <div
            v-else
            class="flex gap-3 sm:gap-4 w-full justify-center pb-6"
          >
            <button
              type="button"
              class="px-6 py-3.5 bg-neutral-white/5 border border-neutral-white/10 rounded-2xl text-neutral-white hover:bg-secondary-light transition-all font-bold uppercase tracking-widest text-[10px] disabled:opacity-50"
              :disabled="isDownloading"
              @click="handleDownload"
            >
              {{ isDownloading ? 'Downloading…' : 'Download' }}
            </button>
            <button
              type="button"
              class="w-12 h-12 bg-secondary-light rounded-2xl flex items-center justify-center text-neutral-white hover:scale-105 transition-all"
              aria-label="Share image"
              @click="handleShare"
            >
              <Share2 class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
