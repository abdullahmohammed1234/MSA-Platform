<script setup lang="ts">
useAttrs: false;
import { ref, computed, onMounted } from 'vue';
import { 
  MapPin, 
  ArrowRight,
  Sparkles,
  GraduationCap,
  Users, 
  BookOpen, 
  Heart, 
  Globe,
  Plus
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import ParallaxSection from '@/components/shared/ParallaxSection.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';
import { usePrayerTimes, campusPrayerInfo, fallbackPrayerTimes, prayerTabs, jumuahSessions, type Campus, type PrayerTab } from '@/composables/usePrayerTimes';
import { useSeo } from '@/composables/useSeo';
import websiteService, { type EventItem } from '@/services/website/websiteService';
import { HERO_IMAGES, resolvePublicImagePath } from '@/constants/publicAssets';

// SEO Settings
useSeo({
  title: 'Simon Fraser University MSA | Home',
  description: 'Nurturing student success, religious scholarship, and active community outreach at Simon Fraser University since 1977.'
});

const activeTab = ref<PrayerTab>('Burnaby');
const { times, isLoading, error } = usePrayerTimes();

const isCampusTab = computed(() => activeTab.value !== "Jumu'ah");
const activeData = computed(() => (isCampusTab.value ? campusPrayerInfo[activeTab.value as Campus] : null));
const activeTimes = computed(() => {
  if (!isCampusTab.value) return [];
  return times.value[activeTab.value as Campus] ?? fallbackPrayerTimes;
});

const featuredEvents = ref<EventItem[]>([]);
const homepageData = ref<any>(null);

onMounted(async () => {
  try {
    const events = await websiteService.getEvents();
    // Pick the first two events for showcase
    featuredEvents.value = events.slice(0, 2);
  } catch (err) {
    console.error('Failed to load events for Home page:', err);
  }

  try {
    homepageData.value = await websiteService.getHomepageData();
  } catch (err) {
    console.warn('API getHomepageData failed, using static fallback content.', err);
  }
});

// Dynamic Homepage CMS Mappings
const heroTagline = computed(() => homepageData.value?.hero?.tagline ?? 'Simon Fraser University');
const heroTitle = computed(() => homepageData.value?.hero?.title ?? 'Building Faith & Community at SFU');
const heroImg = computed(() =>
  resolvePublicImagePath(homepageData.value?.hero?.background_image ?? HERO_IMAGES.foto2),
);
const heroCta1Text = computed(() => homepageData.value?.hero?.cta_primary_text ?? 'Join the Community');
const heroCta1Url = computed(() => homepageData.value?.hero?.cta_primary_url ?? '/membership');
const heroCta2Text = computed(() => homepageData.value?.hero?.cta_secondary_text ?? 'Explore Events');
const heroCta2Url = computed(() => homepageData.value?.hero?.cta_secondary_url ?? '/events');

const offeringsSubtitle = computed(() => homepageData.value?.offerings?.section_subtitle ?? 'Our Framework');
const offeringsTitle = computed(() => homepageData.value?.offerings?.section_title ?? 'What we Provide');

const dynamicOfferings = computed(() => {
  if (!homepageData.value?.offerings) {
    return [
      { 
        title: 'Mentorship', 
        desc: 'Personalized guidance from upper-year students to help you navigate campus life.',
        icon: GraduationCap 
      },
      { 
        title: 'Chaplaincy', 
        desc: 'Spiritual support and counseling for faith-centered needs and emotional well-being.',
        icon: Sparkles 
      },
      { 
        title: 'Education & Dawah', 
        desc: 'Weekly halaqas, workshops, and outreach that connect MSA life with dawah on campus.',
        icon: BookOpen 
      },
    ];
  }
  
  const iconMap: Record<string, any> = { GraduationCap, Sparkles, BookOpen, Users, Heart, Globe };
  return [
    { 
      title: homepageData.value.offerings.offering_1_title || 'Mentorship', 
      desc: homepageData.value.offerings.offering_1_desc || '', 
      icon: iconMap[homepageData.value.offerings.offering_1_icon] || GraduationCap 
    },
    { 
      title: homepageData.value.offerings.offering_2_title || 'Chaplaincy', 
      desc: homepageData.value.offerings.offering_2_desc || '', 
      icon: iconMap[homepageData.value.offerings.offering_2_icon] || Sparkles 
    },
    { 
      title: homepageData.value.offerings.offering_3_title || 'Education & Dawah', 
      desc: homepageData.value.offerings.offering_3_desc || '', 
      icon: iconMap[homepageData.value.offerings.offering_3_icon] || BookOpen 
    },
  ];
});

const ctaTitle = computed(() => homepageData.value?.cta?.title ?? 'Be part of something Meaningful');
const ctaSubtitle = computed(() => homepageData.value?.cta?.subtitle ?? "Join a thriving support structure dedicated to representing Muslim students, facilitating Jumu'ah services, and fostering a community of growth.");
const ctaBtnText = computed(() => homepageData.value?.cta?.button_text ?? 'Join the MSA Family');
const ctaBtnUrl = computed(() => homepageData.value?.cta?.button_url ?? '/membership');
</script>

<template>
  <div class="pb-32 bg-neutral-background">
    <!-- 1. HERO SECTION -->
    <section class="relative min-h-[95vh] flex items-center justify-center overflow-hidden pt-28">
      <div class="absolute inset-0 z-0">
        <ParallaxSection :offset="120" class="w-full h-[125%] -top-[12%] absolute">
          <img 
            :src="heroImg" 
            class="w-full h-full object-cover scale-105"
            alt="SFU Campus Life"
            loading="lazy"
          />
        </ParallaxSection>
        <!-- Rich multi-layer overlay for cinematic lighting -->
        <div class="absolute inset-0 bg-gradient-to-b from-primary/85 via-primary/40 to-neutral-background z-10" />
        <div class="absolute inset-0 bg-gradient-to-r from-primary-dark/80 via-transparent to-primary-dark/65 z-10 mix-blend-multiply" />
        <div class="absolute inset-0 pattern-islamic opacity-[0.03] z-10" />
      </div>
      
      <!-- Decorative Glass Elements -->
      <FloatingElement class="absolute top-1/4 right-[8%] w-36 h-36 glass rounded-[2.5rem] opacity-35 hidden lg:block" />
      <FloatingElement :delay="1.2" class="absolute bottom-1/4 left-[6%] w-28 h-28 bg-accent-gold/15 blur-2xl rounded-full hidden lg:block" />
      <FloatingElement :delay="2.5" class="absolute top-1/3 left-[12%] w-20 h-20 border border-white/15 rounded-full hidden lg:block" />

      <div class="container-custom relative z-20 text-center">
        <ScrollReveal direction="down" width="100%">
          <div class="inline-flex items-center gap-2.5 px-5 py-2.5 bg-white/10 border border-white/10 backdrop-blur-md rounded-full text-accent-gold text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.2em] mb-8 mx-auto">
            <Sparkles :size="14" class="animate-pulse" />
            {{ heroTagline }}
          </div>
        </ScrollReveal>
        
        <ScrollReveal :delay="0.15" direction="up" width="100%">
          <h1 
            class="text-5xl sm:text-7xl md:text-[96px] lg:text-[115px] font-display font-black text-white leading-[0.95] tracking-tight mb-10 text-balance"
            v-html="heroTitle"
          ></h1>
        </ScrollReveal>

        <ScrollReveal :delay="0.3" direction="up" width="100%">
          <div class="flex flex-col sm:flex-row justify-center gap-5 sm:gap-6">
            <router-link :to="heroCta1Url">
              <PublicButton variant="gold" size="lg" class="shadow-2xl shadow-primary-dark/45 font-bold w-full sm:w-auto">
                {{ heroCta1Text }}
              </PublicButton>
            </router-link>
            <router-link :to="heroCta2Url">
              <PublicButton variant="white" size="lg" class="shadow-2xl bg-white/10 backdrop-blur-md text-white border-white/10 hover:bg-white/20 w-full sm:w-auto">
                {{ heroCta2Text }}
              </PublicButton>
            </router-link>
          </div>
        </ScrollReveal>
      </div>
    </section>

    <!-- 2. PRAYER PREVIEW -->
    <section class="section-padding container-custom relative">
      <div class="absolute top-12 left-10 w-96 h-96 bg-primary-light/5 rounded-full blur-3xl -z-10 pointer-events-none" />
      <div class="flex flex-col lg:flex-row gap-16 lg:gap-24 items-stretch">
         <div class="space-y-10 lg:w-1/2 flex flex-col justify-between">
            <div class="space-y-6">
              <ScrollReveal>
                <div class="space-y-3">
                  <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-secondary">Congregational Space</span>
                  <h2 class="text-4xl sm:text-5xl md:text-6xl font-display font-extrabold text-primary leading-tight">Prayer on <br /><span class="text-secondary italic font-serif font-light">Campus</span></h2>
                </div>
              </ScrollReveal>
              <ScrollReveal :delay="0.15">
                <p class="max-w-lg text-neutral-black/60 font-sans">
                  Join us for daily prayers and Jumu'ah at our three dedicated campus locations. Find spiritual peace right where you study.
                </p>
              </ScrollReveal>
            </div>

            <!-- Campus Selector -->
            <ScrollReveal :delay="0.25">
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1.5 bg-white/70 backdrop-blur border border-neutral-ivory rounded-3xl shadow-soft w-full">
                <button
                  v-for="tab in prayerTabs"
                  :key="tab"
                  @click="activeTab = tab"
                  :class="[
                    'w-full px-2 py-3 sm:px-3 sm:py-3.5 rounded-2xl text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider sm:tracking-widest transition-all cursor-pointer text-center leading-tight',
                    activeTab === tab 
                      ? 'bg-primary text-white shadow-brand' 
                      : 'text-neutral-black/55 hover:text-primary hover:bg-primary/5'
                  ]"
                >
                  {{ tab }}
                </button>
              </div>
            </ScrollReveal>

            <ScrollReveal v-if="isCampusTab && activeData" :delay="0.35" class="w-full">
              <div class="flex gap-5 p-6 bg-white border border-neutral-ivory rounded-3xl hover:border-primary/25 shadow-soft hover:shadow-premium transition-all duration-300 group">
                <div class="p-3 bg-primary/5 rounded-2xl text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-300 h-fit">
                  <MapPin :size="20" class="shrink-0 group-hover:scale-110 transition-transform" />
                </div>
                <div>
                  <h4 class="font-extrabold text-primary text-[11px] uppercase tracking-wider">Location</h4>
                  <p class="text-neutral-black/60 text-xs mt-1.5 font-medium leading-relaxed">{{ activeData.location }}</p>
                </div>
              </div>
            </ScrollReveal>

            <ScrollReveal v-else :delay="0.35" class="w-full">
              <p class="text-neutral-black/60 text-sm font-sans leading-relaxed">
                Friday Jumu'ah prayers at Burnaby and Surrey campuses. Arrive early for setup and khutbah.
              </p>
            </ScrollReveal>

            <div v-if="error" class="text-xs font-bold uppercase tracking-widest text-secondary">{{ error }}</div>

            <ScrollReveal :delay="0.4">
              <router-link to="/prayer" class="inline-flex items-center gap-3 text-secondary font-extrabold text-[11px] uppercase tracking-widest border-b-2 border-accent-gold pb-1.5 group">
                 Full Prayer Schedule <ArrowRight :size="16" class="group-hover:translate-x-1.5 transition-transform" />
              </router-link>
            </ScrollReveal>
         </div>

         <div class="lg:w-1/2 w-full flex items-center">
            <div v-if="isCampusTab" class="grid grid-cols-2 gap-4 sm:gap-5 w-full relative">
              <div 
                v-for="(prayer, i) in activeTimes" 
                :key="`${activeTab}-${prayer.name}`"
                :class="[
                  'p-6 sm:p-8 rounded-[2rem] bg-white border border-neutral-ivory text-center shadow-soft hover:shadow-premium transition-all duration-500 group active:scale-[0.98] cursor-pointer relative overflow-hidden',
                  i === 4 ? 'col-span-2' : ''
                ]"
              >
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-transparent via-primary-light/10 to-transparent scale-x-0 group-hover:scale-x-100 transition-transform duration-500" />
                <div class="text-[9px] uppercase tracking-[0.25em] font-extrabold text-secondary mb-3">{{ prayer.name }}</div>
                <div class="text-3xl sm:text-4xl font-display font-extrabold text-primary mb-2.5 transition-transform group-hover:scale-105 duration-300">{{ prayer.time }}</div>
                <div class="text-[9px] font-bold text-neutral-black/30 uppercase tracking-widest">{{ isLoading ? 'Fetching Today' : 'Starts Today' }}</div>
              </div>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:gap-5 w-full">
              <div
                v-for="session in jumuahSessions"
                :key="session.id"
                class="p-6 sm:p-8 rounded-[2rem] bg-white border border-neutral-ivory shadow-soft hover:shadow-premium transition-all duration-500 group"
              >
                <div class="flex items-start justify-between gap-4 mb-5">
                  <h3 class="text-lg sm:text-xl font-display font-extrabold text-primary">{{ session.name }}</h3>
                  <div class="p-2.5 bg-primary/5 rounded-xl text-primary shrink-0">
                    <MapPin :size="18" />
                  </div>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-secondary mb-4">{{ session.location }}</p>
                <div class="flex flex-wrap gap-3">
                  <div
                    v-for="timing in session.timings"
                    :key="`${session.id}-${timing.label}`"
                    class="px-4 py-2.5 rounded-2xl bg-neutral-background border border-neutral-ivory"
                  >
                    <div class="text-[9px] uppercase tracking-widest font-extrabold text-neutral-black/40">{{ timing.label }}</div>
                    <div class="text-sm font-display font-extrabold text-primary mt-0.5">{{ timing.time }}</div>
                  </div>
                </div>
              </div>
            </div>
         </div>
      </div>
    </section>

    <!-- 3. QUICK OFFERINGS -->
    <section class="section-padding bg-white border-y border-neutral-ivory/50">
      <div class="container-custom">
         <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 mb-16 sm:mb-20">
          <div class="space-y-3">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-secondary">{{ offeringsSubtitle }}</span>
            <h2 class="text-3xl sm:text-5xl font-display font-extrabold text-primary" v-html="offeringsTitle"></h2>
          </div>
          <router-link to="/resources" class="text-primary font-extrabold text-[11px] uppercase tracking-widest flex items-center gap-2 group border-b-2 border-primary/10 pb-1 h-fit">
            View All Services <ArrowRight :size="16" class="group-hover:translate-x-1 transition-transform" />
          </router-link>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 items-stretch">
          <PublicCard 
            v-for="(item, i) in dynamicOfferings" 
            :key="i" 
            variant="default"
            padding="none"
            class="group"
          >
            <div class="p-8 sm:p-10 space-y-6 flex flex-col h-full">
              <div class="w-14 h-14 bg-primary text-white rounded-[1.25rem] flex items-center justify-center group-hover:bg-secondary group-hover:shadow-glow transition-all duration-500">
                <component :is="item.icon" :size="26" />
              </div>
              <div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-primary mb-3 group-hover:text-secondary transition-colors duration-300">{{ item.title }}</h3>
                <p class="text-sm text-neutral-black/60 leading-relaxed font-sans">{{ item.desc }}</p>
              </div>
            </div>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- 4. UPCOMING HIGHLIGHTS -->
    <section class="section-padding container-custom">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 mb-16 sm:mb-20">
        <div class="space-y-3">
          <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-secondary">Events Calendar</span>
          <h2 class="text-3xl sm:text-5xl font-display font-extrabold text-primary">Next <span class="italic font-serif text-secondary font-light">Events</span></h2>
        </div>
        <router-link to="/events" class="text-primary font-extrabold text-[11px] uppercase tracking-widest flex items-center gap-2 group border-b-2 border-primary/10 pb-1 h-fit">
          Full Calendar <ArrowRight :size="16" class="group-hover:translate-x-1 transition-transform" />
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
         <ScrollReveal 
           v-for="(event, i) in featuredEvents" 
           :key="event.id" 
           :delay="i * 0.2" 
           :direction="i % 2 === 0 ? 'left' : 'right'" 
           class="w-full" 
           width="100%"
         >
            <div class="group relative w-full aspect-square sm:aspect-[16/9] rounded-3xl sm:rounded-[2.5rem] overflow-hidden bg-primary shadow-premium active:scale-[0.98] transition-transform duration-500 cursor-pointer">
              <ParallaxSection :offset="20" class="absolute inset-0 w-full h-full">
                <img :src="event.image" class="w-full h-full object-cover opacity-60 group-hover:scale-105 group-hover:opacity-75 transition-all duration-1000" :alt="event.title" loading="lazy" />
              </ParallaxSection>
              <div class="absolute inset-0 bg-gradient-to-t from-primary-dark/95 via-primary-dark/30 to-transparent p-8 sm:p-12 flex flex-col justify-end gap-2.5 pointer-events-none">
                 <div class="text-[10px] sm:text-xs font-bold text-accent-gold uppercase tracking-[0.2em]">{{ event.date }}</div>
                 <h3 class="text-2xl sm:text-3xl lg:text-4xl font-display font-extrabold text-white leading-tight">{{ event.title }}</h3>
              </div>
              <router-link to="/events" class="absolute inset-0 z-10" />
            </div>
         </ScrollReveal>
      </div>
    </section>

    <!-- 5. CALL TO ACTION -->
    <section class="pb-24 pt-12">
      <div class="container-custom">
        <ScrollReveal direction="up" :distance="45" class="w-full" width="100%">
          <div class="bg-primary rounded-[2.5rem] sm:rounded-[40px] px-6 sm:px-8 py-20 sm:py-28 md:p-32 flex flex-col items-center justify-center text-center space-y-8 relative overflow-hidden group w-full shadow-premium">
             <div class="absolute inset-0 pattern-islamic opacity-10 pointer-events-none group-hover:scale-105 transition-transform duration-[1.5s]" />
             
             <!-- Floating CTA elements -->
             <FloatingElement :delay="0.5" class="absolute top-12 left-12 text-white/10 hidden md:block"><Plus :size="40" /></FloatingElement>
             <FloatingElement :delay="1.8" class="absolute bottom-12 right-12 text-white/10 hidden md:block"><Plus :size="40" /></FloatingElement>
 
             <h2 class="text-3xl sm:text-5xl md:text-7xl font-display font-black text-white relative z-10 leading-tight" v-html="ctaTitle"></h2>
             <p class="text-white/70 max-w-lg mx-auto text-sm sm:text-base leading-relaxed relative z-10 font-sans font-light">
               {{ ctaSubtitle }}
             </p>
             <div class="relative z-10">
               <router-link :to="ctaBtnUrl">
                 <PublicButton variant="gold" size="lg" class="shadow-2xl shadow-primary-dark/30 font-bold">
                   {{ ctaBtnText }}
                 </PublicButton>
               </router-link>
             </div>
          </div>
        </ScrollReveal>
      </div>
    </section>
  </div>
</template>
