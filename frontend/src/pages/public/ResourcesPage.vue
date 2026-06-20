<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { 
  Heart, 
  Search, 
  MapPin, 
  BookMarked, 
  Compass, 
  Stethoscope, 
  GraduationCap, 
  Users, 
  Sparkles, 
  ExternalLink,
  MessageSquare,
  Coffee,
  ChevronRight
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';
import { useSeo } from '@/composables/useSeo';
import websiteService, { type ResourceItem } from '@/services/website/websiteService';
import { HERO_IMAGES } from '@/constants/publicAssets';

useSeo({
  title: 'Resource Repository | SFU MSA',
  description: 'Explore our curated resource library, covering campus prayer spaces, halal food guides, mental health directories, revert starter kits, and student accommodation policies.'
});

const CATEGORIES = [
  'All',
  'New Muslim',
  'Student Guides',
  'Prayer',
  'Mental Health',
  'Learning',
  'Campus Survival',
  'Chaplaincy',
  'Community'
];

// Map string keys to Lucide components
const iconMap: Record<string, any> = {
  Sparkles,
  Users,
  GraduationCap,
  BookMarked,
  MapPin,
  Compass,
  Stethoscope,
  Heart,
  Coffee,
  MessageSquare
};

const resources = ref<ResourceItem[]>([]);
const searchQuery = ref('');
const selectedCategory = ref('All');

onMounted(async () => {
  try {
    resources.value = await websiteService.getResources();
  } catch (err) {
    console.error('Failed to load resources:', err);
  }
});

const filteredResources = computed(() => {
  return resources.value.filter(resource => {
    const matchesCategory = selectedCategory.value === 'All' || resource.category === selectedCategory.value;
    const matchesSearch = resource.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                          resource.description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          resource.tags.some(tag => tag.toLowerCase().includes(searchQuery.value.toLowerCase()));
    return matchesCategory && matchesSearch;
  });
});
</script>

<template>
  <div class="pb-32 bg-neutral-background">
    <!-- Header Section -->
    <section class="relative min-h-[75vh] flex items-center pt-24 pb-20 overflow-hidden border-b border-neutral-gray/20 bg-primary">
      <div class="absolute inset-0 z-0">
        <img 
          :src="HERO_IMAGES.msaSs2024_87" 
          class="absolute inset-0 w-full h-full object-cover opacity-80 grayscale-[15%]"
          alt="Resources Hero"
        />
        <div class="absolute inset-0 bg-linear-to-b from-primary/80 via-primary/25 to-primary/85 z-10" />
        <div class="absolute top-0 right-0 w-1/2 h-full bg-secondary/5 rounded-bl-[20rem] blur-3xl opacity-20" />
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl space-y-8">
          <ScrollReveal direction="right">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-white/20 border border-white/20 rounded-full text-accent-gold text-[11px] font-bold uppercase tracking-widest backdrop-blur-md">
              <BookMarked :size="14" />
              Resource Repository
            </div>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.2">
            <h1 class="text-6xl md:text-8xl font-display font-medium text-white leading-tight tracking-tighter">
              Knowledge <br /> <span class="text-accent-gold italic font-serif border-b border-accent-gold/30">& Empowerment</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-white/70 text-xl md:text-2xl leading-relaxed max-w-2xl font-light">
              A curated hub of guides, tools, and supports designed specifically for the Muslim student experience at Simon Fraser University.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Filter & Search Section -->
    <div class="sticky top-24 z-30 mb-12 bg-neutral-background/95 backdrop-blur-md border-b border-neutral-ivory/60">
      <section class="container-custom py-4">
        <div class="bg-white/80 backdrop-blur-xl border border-neutral-gray/20 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
        <!-- Search Bar -->
        <div class="relative flex-grow w-full">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-black/20" :size="18" />
          <input 
            type="text" 
            placeholder="Search guides, support, or topics..." 
            v-model="searchQuery"
            class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all text-neutral-black"
          />
        </div>
        
        <!-- Category Scroll (Desktop/Mobile) -->
        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto no-scrollbar py-1">
          <div class="flex items-center space-x-2">
            <button
              v-for="cat in CATEGORIES"
              :key="cat"
              @click="selectedCategory = cat"
              :class="[
                'px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all duration-300 cursor-pointer',
                selectedCategory === cat 
                  ? 'bg-primary text-white shadow-premium' 
                  : 'bg-white text-neutral-black/40 hover:bg-primary/5 border border-neutral-gray/20'
              ]"
            >
              {{ cat }}
            </button>
          </div>
        </div>
        </div>
      </section>
    </div>

    <!-- Results Grid -->
    <section class="container-custom min-h-[40vh]">
      <div v-if="filteredResources.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
        <PublicCard
          v-for="resource in filteredResources"
          :key="resource.id"
          variant="default"
          padding="none"
          :hoverable="true"
          class="h-full"
        >
          <div class="p-8 flex flex-col h-full group">
          <div class="flex justify-between items-start mb-6">
            <div class="w-14 h-14 bg-neutral-background rounded-2xl flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500 border border-neutral-gray/10">
              <component :is="iconMap[resource.iconName] || BookMarked" :size="28" />
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/5 px-3 py-1 rounded-full border border-primary/10">
              {{ resource.category }}
            </span>
          </div>
          
          <div class="flex-1 space-y-3">
            <h3 class="text-xl font-display font-bold text-primary group-hover:text-secondary transition-colors">
              {{ resource.title }}
            </h3>
            <p class="text-sm text-neutral-black/60 leading-relaxed font-light">
              {{ resource.description }}
            </p>
          </div>

          <div class="mt-8 flex items-center justify-between pt-4 border-t border-neutral-ivory/30">
            <div class="flex gap-2">
              <span v-for="tag in resource.tags.slice(0, 2)" :key="tag" class="text-[9px] text-neutral-black/30 font-mono">#{{ tag }}</span>
            </div>
            <a 
              v-if="resource.isExternal"
              :href="resource.link" 
              target="_blank" 
              rel="noreferrer"
              class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-primary hover:text-secondary transition-colors cursor-pointer"
            >
              Visit Site <ExternalLink :size="12" />
            </a>
            <router-link 
              v-else
              :to="resource.link"
              class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-primary hover:text-secondary transition-colors cursor-pointer"
            >
              Learn More <ChevronRight :size="14" />
            </router-link>
          </div>
          </div>
        </PublicCard>
      </div>
      
      <div v-else class="flex flex-col items-center justify-center py-20 text-center space-y-6">
        <div class="w-20 h-20 bg-primary/5 rounded-full flex items-center justify-center text-primary/20">
          <Search :size="40" />
        </div>
        <div class="space-y-2">
          <h3 class="text-2xl font-display font-bold text-primary">No resources found</h3>
          <p class="text-neutral-black/30">Try adjusting your search or category filters.</p>
        </div>
        <button 
          @click="searchQuery = ''; selectedCategory = 'All';"
          class="text-primary font-bold uppercase text-xs tracking-widest hover:underline cursor-pointer"
        >
          Clear all filters
        </button>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="mt-32 px-5 sm:px-8 md:px-12">
      <ScrollReveal direction="up" :distance="50" width="100%">
        <div class="bg-primary py-24 md:py-32 px-6 md:px-12 rounded-[3rem] md:rounded-[4rem] relative overflow-hidden group border border-secondary/10 shadow-premium max-w-6xl mx-auto">
          <div class="absolute inset-0 pattern-islamic opacity-10 group-hover:scale-110 transition-transform duration-1000" />
          <div class="relative z-10 text-center space-y-8 max-w-4xl mx-auto">
            <div class="inline-flex px-6 py-2 border border-white/20 rounded-full text-white/40 text-[10px] uppercase tracking-widest">
              Community Contributions
            </div>
            <h2 class="text-4xl md:text-6xl font-display font-medium text-white mx-auto leading-tight">
              Have a resource we <span class="text-accent-gold italic font-serif">should include?</span>
            </h2>
            <p class="text-white/60 max-w-xl mx-auto text-lg font-light">
              Our library grows through community knowledge. If you've found a guide, a halal gem, or a student tip, share it with the brotherhood and sisterhood.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
              <router-link to="/contact">
                <PublicButton variant="secondary" size="lg" class="bg-secondary hover:bg-secondary-light text-white shadow-premium">
                  Submit Resource
                </PublicButton>
              </router-link>
              <router-link to="/contact">
                <PublicButton variant="outline" size="lg" class="border-white/20 text-white hover:bg-white/10">
                  Contact Chaplain
                </PublicButton>
              </router-link>
            </div>
          </div>
        </div>
      </ScrollReveal>
    </section>
  </div>
</template>
