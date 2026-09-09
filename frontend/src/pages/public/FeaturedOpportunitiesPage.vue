<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { websiteService } from '@/services/website/websiteService';
import type { PublicFeaturedOpportunity } from '@/services/website/websiteService';
import { 
  Sparkles, 
  BookOpen, 
  ExternalLink, 
  CheckCircle2, 
  Compass, 
  AlertCircle 
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';

const opportunities = ref<PublicFeaturedOpportunity[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);

const loadOpportunities = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    opportunities.value = await websiteService.getFeaturedOpportunities();
  } catch (err: any) {
    console.error('Failed to load featured opportunities:', err);
    error.value = 'Failed to load opportunities. Please refresh or check back shortly.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadOpportunities();
});
</script>

<template>
  <div class="bg-neutral-background min-h-screen overflow-x-hidden w-full">
    <!-- Hero Section -->
    <section class="relative py-24 sm:py-32 bg-primary text-white overflow-hidden border-b border-primary/10">
      <div class="absolute inset-0 pattern-islamic opacity-5" />
      <div class="absolute top-0 right-0 w-96 h-96 bg-secondary-light/10 blur-[120px] rounded-full pointer-events-none" />

      <div class="container-custom relative z-10">
        <div class="max-w-4xl space-y-8">
          <ScrollReveal direction="right">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-accent-gold font-bold uppercase tracking-widest text-[10px]">
              <Sparkles class="w-3.5 h-3.5 text-accent-gold shrink-0" /> Community Initiatives & Learning
            </span>
          </ScrollReveal>

          <ScrollReveal :delay="0.2">
            <h1 class="text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-display font-bold leading-tight tracking-tight text-white">
              Learning <span class="text-accent-gold italic font-serif">Resources.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-base sm:text-xl text-white/80 leading-relaxed max-w-2xl font-light">
              Discover educational programs, courses, spiritual development initiatives, and community learning resources highlighted by the SFU MSA.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Opportunities List Section -->
    <section class="py-20">
      <div class="container-custom space-y-12">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 space-y-4">
          <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
          <p class="text-xs font-bold uppercase tracking-widest text-primary/60">Loading Learning Resources...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="max-w-2xl mx-auto p-6 bg-red-50 border border-red-200 text-red-700 rounded-3xl text-center space-y-3">
          <AlertCircle class="w-8 h-8 text-red-600 mx-auto" />
          <p class="text-sm font-bold">{{ error }}</p>
          <button 
            @click="loadOpportunities"
            class="px-6 py-2.5 bg-red-600 text-white rounded-full text-xs font-bold uppercase tracking-widest hover:bg-red-700 transition-colors"
          >
            Retry
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="opportunities.length === 0" class="max-w-2xl mx-auto py-16 px-8 bg-white border border-neutral-ivory rounded-3xl text-center shadow-soft space-y-4">
          <Compass class="w-12 h-12 text-primary/40 mx-auto" />
          <h3 class="text-xl font-bold text-neutral-black">No Active Learning Resources</h3>
          <p class="text-sm text-neutral-black/60 font-light max-w-md mx-auto leading-relaxed">
            There are currently no active learning resources listed. Check back soon for upcoming community programs, courses, and educational initiatives!
          </p>
        </div>

        <!-- Opportunities Grid / Cards -->
        <div v-else class="space-y-12">
          <ScrollReveal 
            v-for="opp in opportunities" 
            :key="opp.id"
            direction="up" 
            width="100%" 
            class="w-full flex justify-center"
          >
            <div class="bg-gradient-to-br from-primary via-primary-dark to-primary text-white rounded-3xl p-8 sm:p-12 shadow-2xl border border-primary/20 relative overflow-hidden grid lg:grid-cols-12 gap-8 items-center w-full">
              <div class="absolute -top-12 -right-12 w-64 h-64 bg-accent-gold/10 rounded-full blur-3xl pointer-events-none" />
              
              <!-- Image Column -->
              <div 
                v-if="opp.featured_image"
                class="lg:col-span-5 relative rounded-2xl overflow-hidden shadow-xl border border-white/10 bg-black/20 backdrop-blur-sm p-3 flex items-center justify-center group min-h-[260px] sm:min-h-[320px]"
              >
                <img
                  :src="opp.featured_image"
                  :alt="opp.title"
                  class="w-full h-full max-h-[380px] object-contain transition-transform duration-700 group-hover:scale-[1.02]"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-primary/30 via-transparent to-transparent opacity-40 pointer-events-none" />
              </div>

              <!-- Text & Action Column -->
              <div :class="opp.featured_image ? 'lg:col-span-7' : 'lg:col-span-12'" class="space-y-5 text-left">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 text-accent-gold font-bold text-xs uppercase tracking-widest border border-white/15 backdrop-blur-md">
                  <BookOpen class="w-3.5 h-3.5 shrink-0" />
                  <span>{{ opp.eyebrow || 'Featured Educational Resource' }}</span>
                </div>

                <h3 class="text-2xl sm:text-4xl font-display font-bold text-white tracking-tight break-words">
                  {{ opp.title }}
                </h3>

                <p v-if="opp.description" class="text-sm sm:text-base text-white/85 leading-relaxed font-light whitespace-pre-line">
                  {{ opp.description }}
                </p>
                <p v-else-if="opp.short_description" class="text-sm sm:text-base text-white/85 leading-relaxed font-light">
                  {{ opp.short_description }}
                </p>

                <!-- Highlighted Features list -->
                <div v-if="opp.features && opp.features.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 text-xs text-white/90">
                  <div 
                    v-for="(feat, fIdx) in opp.features" 
                    :key="fIdx"
                    class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-xl p-3 backdrop-blur-sm"
                  >
                    <CheckCircle2 class="w-4 h-4 text-accent-gold shrink-0" />
                    <span>{{ feat }}</span>
                  </div>
                </div>

                <!-- Call to action button -->
                <div v-if="opp.external_url" class="pt-2">
                  <a
                    :href="opp.external_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2.5 px-6 py-3.5 bg-accent-gold hover:bg-accent-gold/90 text-primary-dark font-extrabold text-xs uppercase tracking-widest rounded-xl transition-all duration-200 shadow-lg hover:shadow-accent-gold/20 hover:-translate-y-0.5"
                  >
                    <span>Visit Program</span>
                    <ExternalLink class="w-4 h-4" />
                  </a>
                </div>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>
  </div>
</template>
