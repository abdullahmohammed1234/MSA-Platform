<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import websiteService from '@/services/website/websiteService';
import { 
  Camera, 
  Maximize2,
  Filter,
  Users,
  Heart,
  Share2
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import ImageLightbox from '@/components/shared/ImageLightbox.vue';
import type { LightboxImage } from '@/types/lightbox';
import { HERO_IMAGES } from '@/constants/publicAssets';
import {
  DISPLAYABLE_MEDIA_GALLERY,
  type MediaGalleryItem,
} from '@/data/mediaGallery';

const GALLERY_DATA = ref<MediaGalleryItem[]>([...DISPLAYABLE_MEDIA_GALLERY]);
const CATEGORIES = computed(() => {
  const cats = new Set(GALLERY_DATA.value.map((item) => item.category));
  return ['All', ...Array.from(cats).sort()];
});

const activeCategory = ref('All');
const selectedImageIndex = ref<number | null>(null);
const visibleItems = ref(6);
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
      GALLERY_DATA.value = [...cmsMedia, ...DISPLAYABLE_MEDIA_GALLERY];
    }
  } catch {
    // Keep static gallery fallback when API is unavailable.
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

const lightboxImages = computed<LightboxImage[]>(() =>
  filteredData.value.map((item) => ({
    id: item.id,
    url: item.url,
    title: item.title,
    description: item.description,
    category: item.category,
    date: item.date,
    downloadFilename: item.url.split('/').pop()?.split('?')[0],
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
              ? 'bg-primary text-neutral-white shadow-soft shadow-primary/20' 
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
          class="group relative rounded-[2rem] overflow-hidden bg-neutral-white shadow-soft border border-neutral-gray/10 hover:shadow-premium transition-all duration-700 cursor-zoom-in break-inside-avoid-column"
          @click="openLightbox(item)"
        >
          <!-- Image -->
          <div class="aspect-[4/5] md:aspect-auto overflow-hidden">
            <img
              :src="item.url"
              :alt="item.title"
              class="w-full h-full object-cover grayscale opacity-80 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-1000 group-hover:scale-110"
              loading="lazy"
              referrerpolicy="no-referrer"
            />
          </div>

          <!-- Overlay -->
          <div class="absolute inset-0 bg-gradient-to-t from-primary/90 via-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 flex flex-col justify-end p-8">
            <div class="space-y-3 transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
              <div class="flex items-center gap-2">
                 <span class="px-3 py-1 bg-secondary-light text-neutral-white text-[9px] font-bold rounded-md uppercase tracking-wider">
                   {{ item.category }}
                 </span>
                 <span class="text-neutral-white/60 text-[9px] font-mono tracking-widest uppercase">{{ item.date }}</span>
              </div>
              <h3 class="text-xl font-display font-medium text-neutral-white">{{ item.title }}</h3>
              <div class="flex gap-4 text-neutral-white/50 pt-2">
                 <button class="hover:text-accent-gold transition-colors"><Heart class="w-[18px] h-[18px]" /></button>
                 <button class="hover:text-accent-gold transition-colors"><Share2 class="w-[18px] h-[18px]" /></button>
                 <button class="ml-auto hover:text-accent-gold transition-colors"><Maximize2 class="w-[18px] h-[18px]" /></button>
              </div>
            </div>
          </div>

          <!-- Aesthetic Border Glow -->
          <div class="absolute inset-0 border border-neutral-white/0 group-hover:border-neutral-white/10 transition-colors pointer-events-none rounded-[2rem]" />
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
