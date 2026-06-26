<script setup lang="ts">
import { ref, computed } from 'vue';
import { 
  Target, Eye, ShieldCheck, HeartPulse, Users, Calendar, 
  Quote, ArrowRight,
  Sparkles, MessageSquare, HandHelping, Compass, Star,
  Plus
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import AnimatedCounter from '@/components/shared/AnimatedCounter.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import ParallaxSection from '@/components/shared/ParallaxSection.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';
import ImageLightbox from '@/components/shared/ImageLightbox.vue';
import { useSeo } from '@/composables/useSeo';
import { HERO_IMAGES } from '@/constants/publicAssets';
import { urlToLightboxImage } from '@/types/lightbox';

useSeo({
  title: 'About Us | SFU MSA',
  description: 'Learn about the legacy of the Simon Fraser University Muslim Students Association, our mission, vision, values, and leadership principles.'
});

const stats = [
  { value: 3, label: 'Campuses', icon: Compass },
  { value: 5000, label: 'Community Members', icon: Users },
  { value: 35, label: 'Weekly Prayers', icon: Plus },
  { value: 20, label: 'Major Events', icon: Calendar },
];

const values = [
  {
    id: 'ihsan',
    title: 'Ihsan (Excellence)',
    desc: 'Striving for the highest quality in everything we do, from our events to our daily interactions.',
    icon: Sparkles,
    color: 'bg-secondary/5 text-secondary border border-secondary/10'
  },
  {
    id: 'ukhuwah',
    title: 'Ukhuwah (Sisterhood / Brotherhood)',
    desc: 'Building a supportive community that feels like family, where every student finds a place of belonging.',
    icon: Users,
    color: 'bg-primary/5 text-primary-light border border-primary-light/10'
  },
  {
    id: 'adalah',
    title: 'Adalah (Justice)',
    desc: 'Upholding fairness and promoting equity within the campus and the broader Vancouver community.',
    icon: ShieldCheck,
    color: 'text-emerald-700 bg-emerald-500/5 border border-emerald-500/10'
  },
  {
    id: 'khidmah',
    title: 'Khidmah (Service)',
    desc: 'Dedicated to serving humanity as a sincere expression of our faith and devotion to God.',
    icon: HandHelping,
    color: 'text-blue-700 bg-blue-500/5 border border-blue-500/10'
  }
];

const impactPhotos = [
  HERO_IMAGES.juneMsa3,
  HERO_IMAGES.jumuahPrayer,
  HERO_IMAGES.msaSs2024_29,
  HERO_IMAGES.msaSs2024_76,
  HERO_IMAGES.msaSs2024_87,
  HERO_IMAGES.sfuMsaFnd2024_38,
];

const selectedPhotoIndex = ref<number | null>(null);

const impactLightboxImages = computed(() =>
  impactPhotos.map((photo, index) =>
    urlToLightboxImage(photo, index, {
      title: `Community Moment ${index + 1}`,
      category: 'Campus Moments',
      description: 'Glimpses into our vibrant tri-campus life.',
      date: '2026',
    }),
  ),
);
</script>

<template>
  <div class="pb-32 bg-neutral-background text-neutral-black">
    <!-- Hero Section -->
    <section class="relative min-h-[85vh] flex items-center pt-28 pb-20 overflow-hidden">
      <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-b from-neutral-background/65 via-neutral-background/25 to-neutral-background z-10" />
        <img 
          :src="HERO_IMAGES.dsc0516" 
          class="w-full h-full object-cover opacity-80"
          alt="SFU Campus Background"
        />
        <div class="absolute top-0 right-0 w-1/2 h-full bg-primary/5 rounded-bl-[20rem] blur-3xl opacity-20 -z-10" />
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-3xl space-y-8">
          <ScrollReveal direction="right">
            <div class="inline-flex items-center gap-2.5 px-4.5 py-2 bg-white border border-neutral-ivory rounded-full text-primary font-bold text-[10px] uppercase tracking-widest shadow-soft">
              <Compass :size="14" class="text-secondary" /> Our Journey & Identity
            </div>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl sm:text-7xl md:text-8xl font-display font-black leading-[0.95] tracking-tight text-primary">
              A Legacy of <br/> <span class="italic font-serif font-light text-secondary">Faith</span> & <span class="underline decoration-accent-gold decoration-4 underline-offset-8">Support</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-lg md:text-xl text-neutral-black/60 leading-relaxed font-sans max-w-xl font-light">
              For nearly five decades, the SFU Muslim Students Association has been the heart of student life—providing a refuge of spirituality, a platform for growth, and a global family for all.
            </p>
          </ScrollReveal>

          <ScrollReveal :delay="0.4" class="flex flex-wrap gap-4">
            <router-link to="/team">
              <PublicButton variant="primary" size="lg" class="group">
                Meet the Team <ArrowRight :size="16" class="ml-2 group-hover:translate-x-1.5 transition-transform" />
              </PublicButton>
            </router-link>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-24 bg-white relative border-y border-neutral-ivory/60">
      <div class="container-custom">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-12">
          <ScrollReveal direction="up" class="h-full">
            <div class="bg-neutral-background border border-neutral-ivory rounded-[2.5rem] p-10 md:p-14 space-y-8 relative overflow-hidden group hover:border-primary/20 transition-all duration-300 shadow-soft h-full">
              <div class="absolute top-0 right-0 opacity-[0.03] p-10 group-hover:opacity-[0.05] transition-opacity duration-300">
                <Target :size="180" class="text-primary" />
              </div>
              <div class="w-14 h-14 bg-primary text-white rounded-[1.25rem] flex items-center justify-center shadow-soft group-hover:scale-105 transition-all">
                <Target :size="28" />
              </div>
              <div class="space-y-4 relative z-10">
                <h3 class="text-3xl font-display font-extrabold text-primary">Our Mission</h3>
                <p class="text-neutral-black/60 text-base sm:text-lg leading-relaxed font-light">
                  To serve as a cornerstone for the spiritual, social, and academic development of Muslim students at SFU. We strive to provide a safe, inclusive environment that empowers students to thrive while deepening their connection to their faith and community.
                </p>
              </div>
            </div>
          </ScrollReveal>

          <ScrollReveal direction="up" :delay="0.2" class="h-full">
            <div class="bg-primary text-white p-10 md:p-14 rounded-[2.5rem] space-y-8 relative overflow-hidden shadow-premium group h-full">
              <div class="absolute top-0 right-0 opacity-[0.05] p-10">
                <Eye :size="180" />
              </div>
              <div class="w-14 h-14 bg-gradient-to-r from-accent-gold to-yellow-500 text-primary-dark rounded-[1.25rem] flex items-center justify-center shadow-soft group-hover:scale-105 transition-all">
                <Eye :size="28" />
              </div>
              <div class="space-y-4 relative z-10">
                <h3 class="text-3xl font-display font-extrabold text-white">Our Vision</h3>
                <p class="text-white/85 text-base sm:text-lg leading-relaxed font-light">
                  To cultivate a vibrant, intellectually stimulated, and spiritually resilient Muslim community that contributes meaningfully to the diversity and excellence of Simon Fraser University and society at large.
                </p>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Large Hero Image Section -->
    <section class="py-24 bg-neutral-background overflow-hidden">
      <div class="container-custom">
        <ScrollReveal direction="up">
          <div class="relative aspect-video rounded-[3rem] overflow-hidden shadow-premium border border-neutral-ivory group">
            <ParallaxSection :offset="45" class="w-full h-[120%] -top-[10%] absolute">
              <img 
                :src="HERO_IMAGES.dsc0516" 
                class="w-full h-full object-cover opacity-90 transition-all duration-[2s] group-hover:scale-105 group-hover:opacity-100" 
                alt="SFU Muslim Community"
              />
            </ParallaxSection>
            <div class="absolute inset-0 bg-primary/15 mix-blend-overlay" />
            <div class="absolute inset-0 bg-gradient-to-t from-primary-dark/85 via-primary-dark/15 to-transparent pointer-events-none" />
            <div class="absolute bottom-8 sm:bottom-14 left-8 sm:left-14 right-8 sm:right-14 flex flex-col md:flex-row justify-between items-end md:items-center gap-6">
               <div class="space-y-2">
                  <h2 class="text-2xl sm:text-4xl md:text-5xl font-display font-extrabold text-white leading-none">A Journey of <span class="text-accent-gold italic font-serif font-light">Excellence.</span></h2>
                  <p class="text-white/70 text-sm sm:text-lg font-light tracking-tight">Rooted in tradition, built for the future.</p>
               </div>
               <div class="flex gap-4">
                  <div class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center bg-neutral-background/20 backdrop-blur-md">
                     <Sparkles class="text-accent-gold" :size="20" />
                  </div>
               </div>
            </div>
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- Campus Statistics -->
    <section class="bg-primary py-24 relative overflow-hidden flex items-center justify-center border-y border-primary-dark/35">
      <div class="absolute inset-0 pattern-islamic opacity-[0.03] scale-125 pointer-events-none" />
      <div class="container-custom relative z-10 w-full">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-12 lg:gap-20 text-white justify-items-center">
          <div 
            v-for="(stat, i) in stats" 
            :key="i"
            class="flex flex-col items-center text-center space-y-4"
          >
            <ScrollReveal :delay="i * 0.08" direction="up" class="w-full flex justify-center">
              <div class="flex flex-col items-center text-center space-y-4">
                <FloatingElement :delay="i * 0.15" class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center text-accent-gold border border-white/10 backdrop-blur-sm">
                  <component :is="stat.icon" :size="26" />
                </FloatingElement>
                <div class="space-y-1.5">
                  <div class="text-4xl md:text-5xl font-display font-black text-white flex items-center justify-center">
                    <AnimatedCounter 
                      :value="stat.value" 
                      :suffix="stat.label === 'Campuses' ? '' : '+'" 
                    />
                  </div>
                  <div class="text-[9px] uppercase tracking-[0.25em] text-white/45 font-extrabold">{{ stat.label }}</div>
                </div>
              </div>
            </ScrollReveal>
          </div>
        </div>
      </div>
    </section>

    <!-- Core Values -->
    <section class="py-28 bg-neutral-background">
      <div class="container-custom space-y-16">
        <div class="max-w-2xl space-y-4">
          <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-secondary">Our Foundations</span>
          <h2 class="text-4xl sm:text-5xl font-display font-black text-primary">Our Pillars of <span class="text-secondary italic font-serif font-light">Excellence</span></h2>
          <p class="text-neutral-black/60 text-lg font-light">Guided by timeless Islamic principles, we build a future centered on student impact.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">
          <PublicCard
            v-for="v in values"
            :key="v.id"
            variant="default"
            padding="none"
            :hoverable="true"
            class="h-full"
          >
            <div class="p-8 flex flex-col h-full">
              <div :class="['w-14 h-14 rounded-[1.25rem] flex items-center justify-center mb-8 group-hover:scale-105 transition-all duration-300', v.color]">
                <component :is="v.icon" :size="26" />
              </div>
              <h4 class="text-2xl font-display font-extrabold mb-4 text-primary leading-tight">{{ v.title }}</h4>
              <p class="text-neutral-black/55 leading-relaxed text-sm font-light font-sans">{{ v.desc }}</p>
            </div>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- Leadership Philosophy -->
    <section class="py-28 bg-white border-y border-neutral-ivory/60">
      <div class="container-custom">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-24 items-center">
           <div class="space-y-10">
              <div class="space-y-4">
                <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-secondary">Sacred Trust</span>
                <h2 class="text-4xl sm:text-5xl font-display font-black leading-tight text-primary">Leadership Through <br /> <span class="text-secondary italic font-serif font-light">Shura & Service</span></h2>
                <p class="text-neutral-black/60 text-lg leading-relaxed font-light font-sans">
                  Our leadership philosophy is rooted in the Islamic concept of <strong>Shura</strong> (consultative decision making) and <strong>Amanah</strong> (sacred trust). We believe every leader is a shepherd responsible for their community.
                </p>
              </div>
              
              <div class="space-y-8">
                 <div 
                   v-for="(item, i) in [
                     { 
                       title: 'Empathetic Mentorship', 
                       desc: 'We prioritize the personal growth of our team members over organizational metrics.',
                       icon: HeartPulse 
                     },
                     { 
                       title: 'Visionary Implementation', 
                       desc: 'Thinking ahead to ensure the MSA remains relevant for decades to come.',
                       icon: Eye 
                     },
                     { 
                       title: 'Spiritual Integrity', 
                       desc: 'Ensuring our work is always sincere and aligned with divine guidance.',
                       icon: Compass 
                     }
                   ]" 
                   :key="i" 
                   class="flex gap-5"
                 >
                    <div class="shrink-0 w-12 h-12 bg-neutral-background rounded-[1.25rem] flex items-center justify-center shadow-soft text-primary border border-neutral-ivory">
                      <component :is="item.icon" :size="20" />
                    </div>
                    <div class="space-y-1">
                      <h5 class="font-extrabold font-display text-primary text-[15px]">{{ item.title }}</h5>
                      <p class="text-sm text-neutral-black/55 leading-relaxed font-light">{{ item.desc }}</p>
                    </div>
                 </div>
              </div>
           </div>

           <div class="relative flex justify-center lg:justify-end">
              <div class="aspect-square bg-primary rounded-[3rem] overflow-hidden rotate-3 relative group shadow-premium border border-neutral-ivory/50 w-full max-w-[450px]">
                <img 
                  :src="HERO_IMAGES.dsc0516" 
                  class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-105 opacity-80" 
                  alt="Team collaboration" 
                />
                <div class="absolute inset-0 bg-primary/15 mix-blend-multiply" />
              </div>
              <div class="absolute inset-0 border-2 border-secondary rounded-[3rem] -rotate-3 -z-10 max-w-[450px] ml-auto" />
              
              <div class="absolute -bottom-8 -left-4 sm:left-4 bg-white p-7 rounded-3xl shadow-premium border border-neutral-ivory max-w-[280px] space-y-3">
                 <Quote class="text-secondary" :size="28" />
                 <p class="text-primary/90 italic font-serif leading-relaxed text-sm">
                   "The leader of a people is their servant."
                 </p>
                 <div class="text-[9px] uppercase tracking-widest font-extrabold text-neutral-black/35">Saying of the Prophet ﷺ</div>
              </div>
           </div>
        </div>
      </div>
    </section>

    <!-- Community Photo Gallery -->
    <section class="py-28 bg-neutral-background">
      <div class="container-custom space-y-16">
        <div class="text-center space-y-3">
          <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-secondary">Campus Moments</span>
          <h2 class="text-4xl sm:text-5xl font-display font-black text-primary">Moments of <span class="text-secondary italic font-serif font-light">Unity</span></h2>
          <p class="text-neutral-black/55 text-lg font-light">Glimpses into our vibrant tri-campus life.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
          <button
            v-for="(photo, i) in impactPhotos" 
            :key="i"
            type="button"
            class="relative aspect-[4/3] rounded-[2rem] overflow-hidden cursor-zoom-in shadow-soft group border border-neutral-ivory/60 bg-primary/5 text-left"
            @click="selectedPhotoIndex = i"
          >
            <img :src="photo" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-all duration-700" alt="Community moment" />
            <div class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
          </button>
        </div>
      </div>
    </section>

    <ImageLightbox
      v-model="selectedPhotoIndex"
      :images="impactLightboxImages"
    />

    <!-- Volunteer CTA -->
    <section class="container-custom my-20">
      <div class="bg-primary rounded-[2.5rem] sm:rounded-[40px] p-10 md:p-20 text-center space-y-10 relative overflow-hidden shadow-premium">
        <div class="absolute top-0 left-0 w-64 h-64 bg-secondary/20 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl opacity-40 pointer-events-none" />
        <div class="absolute bottom-0 right-0 w-64 h-64 bg-secondary/15 rounded-full translate-x-1/2 translate-y-1/2 blur-3xl opacity-40 pointer-events-none" />
        
        <div class="relative z-10 space-y-6 max-w-3xl mx-auto">
          <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 rounded-full text-accent-gold font-bold text-[10px] uppercase tracking-widest border border-white/10 backdrop-blur-sm">
            <Star :size="14" class="animate-pulse" /> Be the Change
          </div>
          <h2 class="text-4xl sm:text-6xl font-display font-black text-white leading-tight">Ready to Impact Your <br /> <span class="italic font-serif text-accent-gold font-light border-b-2 border-accent-gold/20 pb-1">Campus?</span></h2>
          <p class="text-white/75 text-base sm:text-lg leading-relaxed font-sans font-light">
            Whether you have a few hours a month or want to lead a committee, there is a place for you in our mission. Join hundreds of students building a better tomorrow.
          </p>
          <div class="flex flex-col sm:flex-row justify-center items-center gap-4 pt-4">
             <router-link to="/contact">
               <button class="px-8 py-5 bg-secondary text-white rounded-full font-bold uppercase text-[10px] tracking-widest hover:bg-secondary-light transition-all flex items-center gap-3 group cursor-pointer">
                 Apply to Volunteer <MessageSquare :size="16" />
               </button>
             </router-link>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
