<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import websiteService from '@/services/website/websiteService';
import { 
  Camera, 
  Maximize2,
  Filter,
  Users,
  Play,
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import ImageLightbox from '@/components/shared/ImageLightbox.vue';
import SecureMedia from '@/components/shared/SecureMedia.vue';
import type { LightboxImage } from '@/types/lightbox';
import { HERO_IMAGES } from '@/constants/publicAssets';
import {
  DISPLAYABLE_MEDIA_GALLERY,
  type MediaGalleryItem,
} from '@/data/mediaGallery';

const sortGallery = (items: MediaGalleryItem[]): MediaGalleryItem[] => {
  return [...items].sort((a, b) => {
    const timeA = a.created_at ? new Date(a.created_at).getTime() : 0;
    const timeB = b.created_at ? new Date(b.created_at).getTime() : 0;
    if (timeB !== timeA) return timeB - timeA;
    return String(b.id).localeCompare(String(a.id));
  });
};

const GALLERY_DATA = ref<MediaGalleryItem[]>(sortGallery(DISPLAYABLE_MEDIA_GALLERY));
const CATEGORIES = computed(() => {
  const cats = new Set(GALLERY_DATA.value.map((item) => item.category));
  return ['All', ...Array.from(cats).sort()];
});

const activeCategory = ref('All');
const selectedImageIndex = ref<number | null>(null);
const visibleItems = ref(9);
const isInfiniteLoading = ref(false);
const loaderRef = ref<HTMLElement | null>(null);

const filteredData = computed(() => {
  return GALLERY_DATA.value.filter(item => 
    activeCategory.value === 'All' ? true : item.category === activeCategory.value
  );
});

const currentItems = computed(() => {
  return filteredData.value.slice(0, visibleItems.value);
});

let observer: IntersectionObserver | null = null;

onMounted(async () => {
  try {
    const cmsMedia = await websiteService.getMediaGallery();
    if (cmsMedia.length > 0) {
      GALLERY_DATA.value = sortGallery([...cmsMedia, ...DISPLAYABLE_MEDIA_GALLERY]);
    } else {
      GALLERY_DATA.value = sortGallery(DISPLAYABLE_MEDIA_GALLERY);
    }
  } catch {
    GALLERY_DATA.value = sortGallery(DISPLAYABLE_MEDIA_GALLERY);
  }

  observer = new IntersectionObserver((entries) => {
    const first = entries[0];
    if (first.isIntersecting && !isInfiniteLoading.value && visibleItems.value < filteredData.value.length) {
      isInfiniteLoading.value = true;
      setTimeout(() => {
        visibleItems.value += 3;
        isInfiniteLoading.value = false;
      }, 1000);
    }
  }, { threshold: 0.1 });

  if (loaderRef.value) {
    observer.observe(loaderRef.value);
  }
});

onUnmounted(() => {
  if (observer) {
    observer.disconnect();
  }
});

const handleCategoryChange = (cat: string) => {
  activeCategory.value = cat;
  visibleItems.value = 6;
  selectedImageIndex.value = null;
};

const isVideoItem = (item: MediaGalleryItem) =>
  item.media_type === 'video' || !!item.mime_type?.startsWith('video/');

const lightboxImages = computed<LightboxImage[]>(() =>
  filteredData.value.map((item) => ({
    id: item.id,
    url: item.url,
    title: undefined,
    description: item.description,
    category: item.category,
    date: item.date,
    downloadFilename: item.url.split('/').pop()?.split('?')[0],
    mediaType: isVideoItem(item) ? 'video' : 'image',
  })),
);

const openLightbox = (item: MediaGalleryItem) => {
  const index = filteredData.value.findIndex((entry) => entry.id === item.id);
  selectedImageIndex.value = index >= 0 ? index : 0;
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background selection:bg-secondary-light/30 selection:text-primary pb-32">
    <!-- Hero Section -->
    <section class="relative h-[80vh] flex items-center justify-center overflow-hidden bg-primary">
      <div class="absolute inset-0">
        <img 
          :src="HERO_IMAGES.foto2" 
          alt="Hero Background"
          class="absolute inset-0 w-full h-full object-cover grayscale-[10%] brightness-[0.8]"
          referrerpolicy="no-referrer"
        />
      </div>
      <div class="absolute inset-0 bg-gradient-to-b from-primary/75 via-primary/20 to-primary/80" />
      
      <div class="relative z-10 text-center space-y-8 container-custom">
        <ScrollReveal direction="down">
          <div class="inline-flex items-center gap-3 px-5 py-2 bg-neutral-white/10 backdrop-blur-md rounded-full text-neutral-white text-[10px] font-bold uppercase tracking-widest border border-neutral-white/20">
            <Camera class="w-4 h-4 text-accent-gold" />
            The Visual Archive
          </div>
        </ScrollReveal>
        
        <ScrollReveal :delay="0.2">
          <h1 class="text-6xl md:text-[8rem] font-display font-medium text-neutral-white leading-[0.9] tracking-tighter">
            Moments that <br />
            <span class="text-accent-gold italic font-serif">Shape the Soul.</span>
          </h1>
        </ScrollReveal>
        
        <ScrollReveal :delay="0.4">
          <p class="text-neutral-white/70 max-w-xl mx-auto text-lg font-light leading-relaxed px-4">
            Capturing the essence of spirituality, community, and growth within the busy life of SFU students.
          </p>
        </ScrollReveal>
      </div>
      
      <!-- Scroll Indicator -->
      <FloatingElement :delay="1" class="absolute bottom-10 left-1/2 -translate-x-1/2 text-neutral-white/40 flex flex-col items-center gap-2">
        <span class="text-[10px] uppercase tracking-tighter font-sans font-black">Explore Archive</span>
        <div class="w-[1px] h-12 bg-gradient-to-b from-accent-gold/40 to-transparent" />
      </FloatingElement>
    </section>

    <!-- Filter Bar -->
    <div class="sticky top-20 z-40 bg-neutral-white/95 backdrop-blur-xl border-y border-neutral-gray/20">
      <div class="container-custom py-6 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-3 text-neutral-black/40">
          <Filter class="w-4 h-4" />
          <span class="text-xs font-bold uppercase tracking-widest font-sans">Collections</span>
        </div>
        
        <div class="flex flex-wrap justify-center gap-2">
          <button
            v-for="cat in CATEGORIES"
            :key="cat"
            @click="handleCategoryChange(cat)"
            class="px-6 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all duration-300"
            :class="activeCategory === cat 
              ? 'bg-primary text-neutral-white' 
              : 'bg-neutral-background text-neutral-black/40 hover:bg-neutral-gray/10 border border-neutral-gray/20'"
          >
            {{ cat }}
          </button>
        </div>
        
        <div class="hidden lg:flex items-center gap-4 text-neutral-black/40">
          <Users class="w-4 h-4" />
          <span class="text-xs font-bold uppercase tracking-widest font-sans">{{ filteredData.length }} Perspectives</span>
        </div>
      </div>
    </div>

    <!-- Masonry Gallery -->
    <section class="container-custom py-20">
      <div
        v-if="filteredData.length === 0"
        class="rounded-[2rem] border border-dashed border-neutral-gray/30 bg-neutral-white px-8 py-20 text-center"
      >
        <Camera class="w-10 h-10 mx-auto mb-4 text-neutral-black/20" />
        <h3 class="text-2xl font-display text-primary mb-3">No photos in this collection yet</h3>
        <p class="text-neutral-black/50 max-w-lg mx-auto font-light">
          Add web-ready images (.webp, .jpeg, .png) to the matching folder under
          <code class="text-xs">public/Media/</code>.
        </p>
      </div>

      <div v-else class="columns-1 md:columns-2 lg:columns-3 gap-8 space-y-8">
        <div
          v-for="item in currentItems"
          :key="item.id"
          class="group rounded-3xl overflow-hidden bg-white shadow-soft border border-neutral-gray/15 hover:shadow-premium transition-all duration-500 cursor-zoom-in break-inside-avoid-column"
          @click="openLightbox(item)"
        >
          <!-- Media preview: Full color, clean photo display -->
          <div class="relative overflow-hidden bg-neutral-black/5 aspect-[4/3]">
            <SecureMedia
              :src="item.url"
              :alt="item.title || 'Media photo'"
              :is-video="isVideoItem(item)"
              media-class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]"
            />

            <!-- Video Play Indicator -->
            <div
              v-if="isVideoItem(item)"
              class="absolute inset-0 flex items-center justify-center pointer-events-none"
            >
              <span class="w-12 h-12 rounded-full bg-neutral-black/60 border border-white/40 text-white flex items-center justify-center backdrop-blur-sm shadow-md">
                <Play class="w-5 h-5 fill-current ml-0.5" />
              </span>
            </div>

            <!-- Audience restriction badge overlay (Brothers Only / Sisters Only) -->
            <div
              v-if="item.audience && item.audience !== 'both'"
              class="absolute top-3 left-3 z-10 pointer-events-none"
            >
              <span
                class="inline-block text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full backdrop-blur-md shadow-sm"
                :class="(item.audience === 'brothers' || item.audience === 'male')
                  ? 'bg-blue-900/80 text-blue-100 border border-blue-400/30'
                  : 'bg-purple-900/80 text-purple-100 border border-purple-400/30'"
              >
                {{ (item.audience === 'brothers' || item.audience === 'male') ? 'Brothers Only' : 'Sisters Only' }}
              </span>
            </div>

            <!-- Subtle hover expand icon -->
            <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
              <span class="p-2 rounded-full bg-white/90 shadow-soft text-primary flex items-center justify-center backdrop-blur-sm">
                <Maximize2 class="w-3.5 h-3.5" />
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Load More / Sentinel -->
      <div 
        v-if="visibleItems < filteredData.length" 
        ref="loaderRef" 
        class="py-20 flex flex-col items-center gap-6"
      >
        <div class="flex gap-2">
          <div
            v-for="i in [0, 1, 2]"
            :key="i"
            class="w-3 h-3 bg-secondary-light rounded-full animate-bounce"
            :style="{ animationDelay: `${i * 0.1}s` }"
          />
        </div>
        <span class="text-[10px] uppercase tracking-[0.3em] font-black text-neutral-black/20">Curating Moments...</span>
      </div>
    </section>

    <ImageLightbox
      v-model="selectedImageIndex"
      :images="lightboxImages"
    />
  </div>
</template>

<style scoped>
.break-inside-avoid-column {
  break-inside: avoid-column;
}
</style>
