<script setup lang="ts">
import { 
  MapPin, 
  Info, 
  Clock, 
  Droplets, 
  Map as MapIcon, 
  Moon, 
  Sparkles,
  ArrowRight
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import ParallaxSection from '@/components/shared/ParallaxSection.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';
import { usePrayerTimes, campusPrayerInfo, fallbackPrayerTimes, jumuahSessions } from '@/composables/usePrayerTimes';
import type { Campus } from '@/composables/usePrayerTimes';
import { HERO_IMAGES } from '@/constants/publicAssets';

const { times, isLoading, error } = usePrayerTimes();
const campuses: Campus[] = ['Burnaby', 'Surrey', 'Vancouver'];

const etiquette = [
  { title: "Shoes Off", desc: "Please leave shoes outside in the designated racks.", icon: Info },
  { title: "Quiet Zone", desc: "No loud talking or phone calls inside the prayer area.", icon: Info },
  { title: "Cleanliness", desc: "Ensure your wudu area is dry and mats are neatly tucked.", icon: Info },
  { title: "Respect", desc: "Be mindful of those currently praying or meditating.", icon: Info },
];
</script>

<template>
  <div class="bg-neutral-background min-h-screen">
    <!-- --- Large Interactive Hero --- -->
    <section class="relative min-h-[70vh] flex items-center pt-24 pb-20 overflow-hidden border-b border-neutral-gray/20 bg-primary">
      <div class="absolute inset-0 z-0">
        <img 
          :src="HERO_IMAGES.jumuahPrayer" 
          class="absolute inset-0 w-full h-full object-cover opacity-80 grayscale-[10%]"
          alt="Prayer Space"
          referrerpolicy="no-referrer"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/80 via-primary/25 to-primary/85 z-10" />
        <div class="absolute top-0 right-0 w-1/2 h-full bg-secondary-light/5 rounded-bl-[20rem] blur-3xl opacity-20" />
      </div>
      
      <div class="container-custom relative z-10">
        <div class="max-w-4xl space-y-6 sm:space-y-8">
          <ScrollReveal direction="right">
            <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-neutral-white/10 text-accent-gold font-bold text-[10px] sm:text-xs uppercase tracking-widest border border-neutral-white/20 backdrop-blur-md">
              <Moon class="w-3.5 h-3.5" />
              Spiritual Life at SFU
            </div>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.2">
            <h1 class="text-4xl sm:text-6xl md:text-[92px] font-light leading-[1.1] sm:leading-[1] tracking-tight text-neutral-white font-display">
              Spaces for <span class="italic font-serif text-accent-gold border-b border-accent-gold/30">Prayer</span> <br class="hidden sm:block" />
              and <span class="font-semibold text-neutral-white underline decoration-accent-gold/30 underline-offset-8">Reflection</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-lg sm:text-xl md:text-2xl text-neutral-white/70 leading-relaxed font-sans max-w-2xl font-light">
              We provide dedicated prayer spaces across all three SFU campuses with daily prayer times calculated for Burnaby, Surrey, and Vancouver.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="section-padding space-y-32">
      <!-- Today's Prayer Times -->
      <section class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Today's Prayer Times
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            Daily start times are calculated for each SFU campus using Vancouver timezone settings.
          </p>
        </div>

        <p v-if="error" class="mb-8 text-xs font-bold uppercase tracking-widest text-secondary-light">{{ error }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 text-neutral-black items-stretch">
          <PublicCard
            v-for="campus in campuses"
            :key="campus"
            variant="default"
            padding="none"
            class="h-full"
          >
            <div class="p-8 space-y-6 flex flex-col h-full">
              <div class="space-y-2">
                <h3 class="text-2xl font-bold text-primary">{{ campusPrayerInfo[campus].name }}</h3>
                <p class="text-sm text-neutral-black/50">{{ campusPrayerInfo[campus].location }}</p>
              </div>

              <div class="space-y-3">
                <div 
                  v-for="prayer in (times[campus] || fallbackPrayerTimes)" 
                  :key="prayer.name" 
                  class="flex items-center justify-between border-b border-neutral-gray/10 pb-3 last:border-b-0 last:pb-0"
                >
                  <span class="text-[10px] font-black uppercase tracking-[0.2em] text-secondary-light">{{ prayer.name }}</span>
                  <span class="font-display text-2xl text-primary">{{ prayer.time }}</span>
                </div>
              </div>

              <div class="text-[10px] font-black uppercase tracking-[0.2em] text-neutral-black/30">
                {{ isLoading ? "Fetching Today" : "Calculated for Today" }}
              </div>
            </div>
          </PublicCard>
        </div>
      </section>
      
      <!-- Locations & Jumu'ah -->
      <section class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Campus Musallas
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            Find a peaceful place to pray, no matter where you are on campus.
          </p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 text-neutral-black items-stretch">
          <PublicCard 
            v-for="campus in campuses" 
            :key="campus" 
            variant="default" 
            padding="none" 
            class="h-full group"
          >
            <div class="p-10 space-y-8 flex flex-col h-full flex-grow">
              <div class="flex items-start justify-between">
                <div class="p-4 bg-primary/5 rounded-2xl text-primary group-hover:bg-primary group-hover:text-neutral-white transition-colors ring-4 ring-transparent group-hover:ring-primary/10 border border-primary/10">
                  <MapPin class="w-6 h-6" />
                </div>
                <a 
                  :href="campusPrayerInfo[campus].mapUrl" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  class="text-xs font-bold uppercase tracking-widest text-neutral-black/40 hover:text-primary transition-colors flex items-center gap-2"
                >
                  Campus Map 
                  <ArrowRight class="w-3 h-3 group-hover:translate-x-1 transition-transform" />
                </a>
              </div>
              
              <div class="space-y-2">
                <h3 class="text-2xl font-bold group-hover:text-primary transition-colors">{{ campusPrayerInfo[campus].name }}</h3>
                <p class="text-sm text-neutral-black/60 bg-neutral-background px-3 py-1 rounded-lg inline-block border border-neutral-gray/10">
                  {{ campusPrayerInfo[campus].location }}
                </p>
              </div>

              <div class="space-y-4">
                <h4 class="text-[10px] uppercase tracking-[0.2em] font-bold text-primary">Facilities</h4>
                <ul class="space-y-3">
                  <li 
                    v-for="(feature, f) in campusPrayerInfo[campus].rooms" 
                    :key="f" 
                    class="flex items-start gap-3 text-sm text-neutral-black/70"
                  >
                    <div class="w-1.5 h-1.5 rounded-full bg-primary mt-1.5 shrink-0 shadow-glow shadow-primary/50" />
                    {{ feature }}
                  </li>
                </ul>
              </div>

              <div class="p-6 bg-neutral-background rounded-2xl border border-neutral-gray/10 space-y-3 group-hover:border-primary/20 transition-colors">
                <div class="flex items-center gap-2 text-primary">
                  <Droplets class="w-4 h-4 text-primary group-hover:scale-110 transition-transform" />
                  <h5 class="font-bold text-xs uppercase tracking-wider">Wudu Access</h5>
                </div>
                <p class="text-xs text-neutral-black/50 leading-relaxed">
                  {{ campusPrayerInfo[campus].wuduDetails }}
                </p>
              </div>
            </div>
          </PublicCard>
        </div>
      </section>

      <!-- Jumu'ah Schedule -->
      <section class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Jumu'ah Prayer
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            Friday congregational prayer times and locations at Burnaby and Surrey campuses.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 text-neutral-black items-stretch">
          <PublicCard
            v-for="session in jumuahSessions"
            :key="session.id"
            variant="default"
            padding="none"
            class="h-full group"
          >
            <div class="p-10 space-y-8 flex flex-col h-full">
              <div class="p-4 bg-primary/5 rounded-2xl text-primary w-fit group-hover:bg-primary group-hover:text-neutral-white transition-colors">
                <Clock class="w-6 h-6" />
              </div>

              <div class="space-y-2">
                <h3 class="text-2xl font-bold group-hover:text-primary transition-colors">{{ session.name }}</h3>
                <p class="text-sm text-neutral-black/60 bg-neutral-background px-3 py-1 rounded-lg inline-block border border-neutral-gray/10">
                  {{ session.location }}
                </p>
              </div>

              <div class="space-y-4">
                <h4 class="text-[10px] uppercase tracking-[0.2em] font-bold text-primary">Schedule</h4>
                <ul class="space-y-3">
                  <li
                    v-for="timing in session.timings"
                    :key="`${session.id}-${timing.label}`"
                    class="flex items-center justify-between text-sm text-neutral-black/70"
                  >
                    <span class="font-medium">{{ timing.label }}</span>
                    <span class="font-display font-bold text-primary">{{ timing.time }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </PublicCard>
        </div>
      </section>

      <!-- Ramadan & Eid Special Section -->
      <section class="container-custom">
        <ScrollReveal direction="up" :distance="60" class="w-full">
          <div class="bg-primary text-neutral-white rounded-[4rem] p-12 md:p-24 relative overflow-hidden group border border-secondary-dark/10">
            <div class="absolute inset-0 pattern-islamic opacity-5 group-hover:scale-110 transition-transform duration-1000" />
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
              <div class="space-y-10">
                <div class="space-y-4">
                  <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-white/5 text-accent-gold text-[10px] uppercase font-bold tracking-widest border border-neutral-white/10">
                    <Sparkles class="w-3 h-3" /> Special Observances
                  </div>
                  <h2 class="text-5xl md:text-6xl font-display font-light text-neutral-white">
                    Ramadan & <span class="italic font-serif text-accent-gold">Eid</span>
                  </h2>
                  <p class="text-neutral-white/60 text-lg leading-relaxed max-w-lg">
                    Join us for communal iftars, Taraweeh prayers, and festive Eid celebrations organized annually by the MSA.
                  </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                  <div class="space-y-3">
                    <FloatingElement :delay="0" class="w-10 h-10 rounded-xl bg-neutral-white/5 flex items-center justify-center text-accent-gold ring-1 ring-neutral-white/10">
                      <Moon class="w-5 h-5" />
                    </FloatingElement>
                    <h4 class="font-bold text-xl text-neutral-white">Ramadan Services</h4>
                    <p class="text-sm text-neutral-white/40 leading-relaxed">
                      Daily iftars on campus, curated prayer schedules, and Suhoor support for residents.
                    </p>
                  </div>
                  <div class="space-y-3">
                    <FloatingElement :delay="1" class="w-10 h-10 rounded-xl bg-neutral-white/5 flex items-center justify-center text-accent-gold ring-1 ring-neutral-white/10">
                      <Sparkles class="w-5 h-5" />
                    </FloatingElement>
                    <h4 class="font-bold text-xl text-neutral-white">Eid Celebrations</h4>
                    <p class="text-sm text-neutral-white/40 leading-relaxed">
                      Eid-ul-Fitr and Eid-ul-Adha gatherings with prayers, food, and community bonding.
                    </p>
                  </div>
                </div>

                <PublicButton variant="gold" size="lg" class="bg-accent-gold hover:bg-accent-gold text-primary">
                  Stay Updated for 2026
                </PublicButton>
              </div>

              <div class="relative">
                <div class="aspect-[4/5] rounded-[3rem] overflow-hidden group/img border border-accent-gold/20 shadow-glow shadow-accent-gold/10">
                  <ParallaxSection :offset="30" class="w-full h-full">
                    <img 
                      src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" 
                      alt="Ramadan vibe"
                      class="w-full h-full object-cover transition-transform duration-1000 group-hover/img:scale-110 grayscale-[20%]"
                      referrerpolicy="no-referrer"
                    />
                  </ParallaxSection>
                </div>
                <FloatingElement :delay="0.5" class="absolute -bottom-10 -left-10 bg-neutral-white p-8 rounded-3xl shadow-2xl max-w-[240px] text-neutral-black border border-neutral-gray/20">
                  <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-primary/10 rounded-lg text-primary">
                      <MapIcon class="w-4 h-4" />
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider">Announcement</span>
                  </div>
                  <p class="text-sm font-medium">Eid-ul-Adha Prayer will be held at the West Gym. Watch for details!</p>
                </FloatingElement>
              </div>
            </div>
          </div>
        </ScrollReveal>
      </section>

      <!-- Etiquette & Chaplain -->
      <section class="container-custom">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
          <div class="space-y-12">
            <div class="max-w-3xl mb-16 space-y-4">
              <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
                Space Etiquette
              </h2>
              <p class="text-lg leading-relaxed text-neutral-black/60">
                To ensure our prayer rooms remain a sanctuary for everyone, please observe the following guidelines.
              </p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-neutral-black">
              <div v-for="(item, i) in etiquette" :key="i" class="flex gap-6 group">
                <div class="mt-1 w-10 h-10 rounded-full border border-neutral-gray/20 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-neutral-white transition-all duration-500 shrink-0">
                  <component :is="item.icon" class="w-[18px] h-[18px]" />
                </div>
                <div class="space-y-1">
                  <h5 class="font-bold text-lg">{{ item.title }}</h5>
                  <p class="text-sm text-neutral-black/60 leading-relaxed font-light">{{ item.desc }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
