<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { 
  ArrowRight, Heart, Sparkles, Shield
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import ImageLightbox from '@/components/shared/ImageLightbox.vue';
import { useSeo } from '@/composables/useSeo';
import websiteService, { type TeamMember } from '@/services/website/websiteService';
import { DEFAULT_TEAM_MEMBERS } from '@/data/teamMembers';
import { HERO_IMAGES, TEAM_FALLBACK_IMAGE } from '@/constants/publicAssets';
import type { LightboxImage } from '@/types/lightbox';

useSeo({
  title: 'Our Team | SFU MSA',
  description: 'Meet the executive council and directors who lead and shape the Simon Fraser University Muslim Students Association.'
});

const DEPARTMENTS = [
  'All',
  'President',
  'Vice Presidents',
  'Directors',
  'Secretary',
  'Coordinators'
];

const teamMembers = ref<TeamMember[]>([...DEFAULT_TEAM_MEMBERS]);
const activeDept = ref('All');
const isLoadingTeam = ref(false);

onMounted(async () => {
  isLoadingTeam.value = true;
  try {
    teamMembers.value = await websiteService.getTeamMembers();
  } catch (err) {
    console.error('Failed to load team members:', err);
    teamMembers.value = [...DEFAULT_TEAM_MEMBERS];
  } finally {
    isLoadingTeam.value = false;
  }
});

const filteredTeam = computed(() => {
  if (activeDept.value === 'All') {
    return teamMembers.value;
  }
  return teamMembers.value.filter(m => m.dept === activeDept.value);
});

const selectedMemberIndex = ref<number | null>(null);

const teamLightboxImages = computed<LightboxImage[]>(() =>
  filteredTeam.value.map((member, index) => ({
    id: `${member.name}-${index}`,
    url: member.img,
    title: member.name,
    subtitle: member.role,
    category: member.dept,
    description: `${member.name} — ${member.role}`,
    downloadFilename: `${member.name.replace(/\s+/g, '-')}.webp`,
  })),
);

const openMemberLightbox = (member: TeamMember) => {
  const index = filteredTeam.value.findIndex((entry) => entry.name === member.name);
  selectedMemberIndex.value = index >= 0 ? index : 0;
};

watch(activeDept, () => {
  selectedMemberIndex.value = null;
});

const values = [
  {
    icon: Shield,
    title: 'Amanah',
    subtitle: 'Sacred Trust',
    description: 'We view our leadership as a trust from Allah and the community, requiring absolute integrity.'
  },
  {
    icon: Sparkles,
    title: 'Ihsan',
    subtitle: 'Excellence',
    description: 'Striving for beauty and perfection in everything we do, from small tasks to major events.'
  },
  {
    icon: Heart,
    title: 'Khidmah',
    subtitle: 'Service',
    description: 'Leading through serving others, prioritizing the needs of our students above all else.'
  }
];
</script>

<template>
  <div class="min-h-screen bg-neutral-background selection:bg-secondary/20">
    <!-- Hero Section - Modern Startup Style -->
    <section class="relative min-h-[80vh] flex items-center pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden border-b border-neutral-gray/20 bg-primary">
      <div class="absolute inset-0 z-0">
        <img 
          :src="HERO_IMAGES.sfuMsaFnd2024_38" 
          class="absolute inset-0 w-full h-full object-cover opacity-80 grayscale-[25%]"
          alt="Team Hero"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/80 via-primary/25 to-primary/85 z-10" />
        <div class="absolute top-0 right-0 w-1/2 h-full bg-secondary/5 rounded-bl-[20rem] blur-3xl opacity-20" />
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl">
          <ScrollReveal direction="right">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 text-accent-gold text-[10px] sm:text-xs font-bold uppercase tracking-widest mb-6 sm:mb-8 border border-white/20 backdrop-blur-md">
              <span class="w-2 h-2 rounded-full bg-accent-gold animate-pulse shadow-glow shadow-accent-gold/50" />
              The Executive Council 2025-2026
            </div>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl sm:text-7xl md:text-9xl font-display font-medium text-white leading-[1] sm:leading-[0.9] tracking-tighter mb-8 sm:mb-10">
              The minds <br class="hidden sm:block" />
              <span class="text-accent-gold italic font-serif border-b border-accent-gold/30">shaping the</span> <br class="hidden sm:block" />
              <span class="font-semibold underline decoration-accent-gold/30 underline-offset-8">MSA legacy.</span>
            </h1>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.3">
            <p class="text-base sm:text-xl md:text-2xl text-white/70 leading-relaxed max-w-2xl font-light">
              A collaborative team of student leaders, creators, and builders 
              dedicated to fostering a vibrant Muslim community at SFU.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Leadership Values Section -->
    <section class="py-20 border-y border-neutral-gray/20 bg-white/50 backdrop-blur-sm">
      <div class="container-custom">
        <div class="flex flex-col md:flex-row gap-12 md:gap-24">
          <div class="md:w-1/3">
            <h2 class="text-3xl font-display font-bold mb-4 uppercase tracking-tight text-primary">Our Leadership <br/>Philosophies</h2>
            <p class="text-neutral-black/40 text-sm leading-relaxed mb-8 font-light">
              We believe that leadership is a responsibility that goes beyond titles. 
              Our team is guided by these core Islamic principles in every decision we make.
            </p>
            <div class="w-12 h-1 bg-secondary rounded-full" />
          </div>
          
          <div class="md:w-2/3 grid grid-cols-1 md:grid-cols-3 gap-8 text-neutral-black">
            <div 
              v-for="v in values" 
              :key="v.title"
              class="space-y-4 group"
            >
              <div class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center group-hover:scale-110 transition-transform duration-500 shadow-lg shadow-primary/20 border border-primary/20">
                <component :is="v.icon" class="w-6 h-6" />
              </div>
              <div>
                <h3 class="text-xl font-bold flex items-center gap-2 text-primary">
                  {{ v.title }}
                  <span class="text-[10px] uppercase font-serif italic text-secondary opacity-60 tracking-widest">{{ v.subtitle }}</span>
                </h3>
                <p class="text-sm text-neutral-black/50 leading-relaxed mt-2 font-light">{{ v.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Department Filter Bar -->
    <div class="sticky top-20 z-40 bg-neutral-background/95 backdrop-blur-xl border-b border-neutral-gray/20">
      <div class="container-custom py-4">
        <div class="flex items-center gap-6 overflow-x-auto pb-2 no-scrollbar">
          <div class="flex-shrink-0 text-[10px] font-black uppercase tracking-[0.3em] text-neutral-black/30 mr-4">Departments:</div>
          <button
            v-for="dept in DEPARTMENTS"
            :key="dept"
            @click="activeDept = dept"
            :class="[
              'flex-shrink-0 px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-all duration-300 relative group cursor-pointer',
              activeDept === dept ? 'text-primary font-bold' : 'text-neutral-black/30 hover:text-primary/60'
            ]"
          >
            {{ dept }}
            <div 
              v-if="activeDept === dept"
              class="absolute -bottom-4 left-0 right-0 h-1 bg-primary rounded-full"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Team Grid -->
    <section class="container-custom py-24">
      <div v-if="filteredTeam.length === 0 && !isLoadingTeam" class="py-16 text-center text-neutral-black/50">
        <p class="text-lg font-display text-primary">No team members to display.</p>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-8 gap-y-20">
        <div 
          v-for="member in filteredTeam" 
          :key="member.name"
          class="group relative"
        >
          <!-- Profile Card -->
          <button
            type="button"
            class="relative mb-8 w-full bg-white rounded-[2.5rem] overflow-hidden shadow-soft group-hover:shadow-premium border border-neutral-gray/10 transition-all duration-700 cursor-zoom-in text-left"
            @click="openMemberLightbox(member)"
          >
            <div class="aspect-[4/5] relative overflow-hidden bg-primary/5">
              <img 
                :src="member.img" 
                :alt="member.name" 
                class="w-full h-full object-cover grayscale opacity-80 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-1000 group-hover:scale-105"
                @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
              />
              <!-- Department Tag Overlay -->
              <div class="absolute top-4 left-4">
                <div class="px-3 py-1 bg-primary/90 backdrop-blur-sm rounded-full text-[9px] font-black uppercase tracking-widest text-white shadow-sm border border-white/10">
                  {{ member.dept }}
                </div>
              </div>
            </div>
          </button>

          <!-- Info Text -->
          <div class="px-2">
            <h3 class="text-2xl font-display font-medium text-primary group-hover:text-secondary transition-colors duration-300">
              {{ member.name }}
            </h3>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-neutral-black/30 mt-1 font-sans">
              {{ member.role }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <ImageLightbox
      v-model="selectedMemberIndex"
      :images="teamLightboxImages"
    />

    <!-- Modern Recruitment Section -->
    <section class="container-custom py-40">
      <ScrollReveal direction="up" :distance="50" class="w-full">
        <div class="relative rounded-[4rem] bg-primary overflow-hidden p-12 md:p-32 text-center shadow-2xl shadow-primary/30 group border border-secondary/10">
          <div class="absolute top-0 left-0 w-full h-full pattern-islamic opacity-5 pointer-events-none group-hover:scale-110 transition-transform duration-1000" />
          <FloatingElement class="absolute -top-24 -right-24 w-96 h-96 bg-secondary opacity-20 blur-[120px] rounded-full" />
          
          <div class="relative z-10 max-w-3xl mx-auto space-y-10">
            <div class="inline-flex w-16 h-16 rounded-full bg-white/10 backdrop-blur-md items-center justify-center text-accent-gold mb-6 ring-1 ring-white/20">
              <Sparkles class="w-8 h-8" />
            </div>
            
            <h2 class="text-5xl md:text-7xl font-display font-semibold text-white leading-none tracking-tighter">
              Help us write the <br />
              <span class="text-accent-gold italic font-serif">next chapter.</span>
            </h2>
            
            <p class="text-white/60 text-lg md:text-xl font-light">
              We're looking for passionate students to lead our committees and 
              bring fresh perspectives to the community.
            </p>
            
            <div class="flex flex-wrap justify-center gap-6 pt-6">
              <router-link to="/contact">
                <PublicButton variant="secondary" size="lg" class="shadow-premium bg-secondary hover:bg-secondary-light text-white">
                  Apply for 2026 Board <ArrowRight :size="20" class="ml-2" />
                </PublicButton>
              </router-link>
              <router-link to="/contact">
                <PublicButton variant="outline" size="lg" class="border-white/20 text-white hover:bg-white/10">
                  View Open Positions
                </PublicButton>
              </router-link>
            </div>
          </div>
        </div>
      </ScrollReveal>
    </section>
    
    <!-- Scroll to Top Hint -->
    <footer class="container-custom pb-20 text-center">
      <p class="text-[10px] font-black uppercase tracking-[0.5em] text-neutral-black/10">
        SFU MSA &bull; Serving Our Students &bull; 2026
      </p>
    </footer>
  </div>
</template>
