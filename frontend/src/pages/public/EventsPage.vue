<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
  Calendar, 
  MapPin, 
  Clock, 
  Search, 
  Bell,
  CalendarDays,
  LayoutGrid,
  List,
  Info
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import ParallaxSection from '@/components/shared/ParallaxSection.vue';
import { useSeo } from '@/composables/useSeo';
import websiteService, { type EventItem } from '@/services/website/websiteService';
import { stripHtml, textPreview } from '@/utils/html';

const REGISTRATION_NOTICE =
  'For registration, please use the Google Forms link provided on the WhatsApp group when ready.';

useSeo({
  title: 'Events Calendar | SFU MSA',
  description: 'Browse upcoming events, Jumu\'ah prayers, social activities, lectures, and workshops organized by Simon Fraser University MSA.'
});

const CATEGORIES = ['All', 'Jummah', 'Social', 'Lecture', 'Workshop', 'Charity', 'Dinner'];

const eventsData = ref<EventItem[]>([]);
const selectedCategory = ref('All');
const searchQuery = ref('');
const viewMode = ref<'grid' | 'list'>('grid');
const now = ref(Date.now());

onMounted(async () => {
  try {
    eventsData.value = await websiteService.getEvents();
  } catch (err) {
    console.error('Failed to load events:', err);
  }
});

function parseDatetimeLocal(dateStr?: string | null): Date {
  if (!dateStr) return new Date(8640000000000000);
  // Strip timezone suffix (Z, +HH:MM, or -HH:MM) to force parsing in local browser time
  const localStr = dateStr.replace(/(Z|([+-]\d{2}:\d{2}))$/, '');
  const parsed = new Date(localStr);
  return Number.isNaN(parsed.getTime()) ? new Date(8640000000000000) : parsed;
}

function getEventStart(event: EventItem): Date {
  if (event.startDate) return parseDatetimeLocal(event.startDate);
  const parsed = new Date(event.date);
  return Number.isNaN(parsed.getTime()) ? new Date(8640000000000000) : parsed;
}

function getEventEnd(event: EventItem): Date {
  if (event.endDate) return parseDatetimeLocal(event.endDate);
  const start = getEventStart(event);
  if (Number.isNaN(start.getTime()) || start.getTime() === 8640000000000000) {
    return new Date(0);
  }
  const end = new Date(start);
  end.setHours(23, 59, 59, 999);
  return end;
}

function isUpcomingEvent(event: EventItem, referenceTime = now.value): boolean {
  return getEventEnd(event).getTime() > referenceTime;
}

const upcomingEvents = computed(() =>
  eventsData.value
    .filter((event) => isUpcomingEvent(event))
    .sort((a, b) => getEventStart(a).getTime() - getEventStart(b).getTime())
);

/** Featured upcoming event takes hero priority; otherwise the next event by start time. */
const heroEvent = computed(() => {
  const upcoming = upcomingEvents.value;
  if (upcoming.length === 0) return null;

  return upcoming.find((event) => event.featured) ?? upcoming[0];
});

const filteredEvents = computed(() => {
  const query = searchQuery.value.toLowerCase();

  return upcomingEvents.value.filter(event => {
    const matchesCategory = selectedCategory.value === 'All' || event.category === selectedCategory.value;
    const plainDescription = stripHtml(event.description).toLowerCase();
    const matchesSearch = event.title.toLowerCase().includes(query) ||
                          plainDescription.includes(query);
    return matchesCategory && matchesSearch;
  });
});

// --- Countdown Timer Logic ---
const timeLeft = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
let timerInterval: number | null = null;

const updateCountdown = () => {
  if (!heroEvent.value) {
    timeLeft.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
    return;
  }

  const distance = getEventStart(heroEvent.value).getTime() - now.value;
  if (distance < 0) {
    timeLeft.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
    return;
  }

  timeLeft.value = {
    days: Math.floor(distance / (1000 * 60 * 60 * 24)),
    hours: Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
    minutes: Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)),
    seconds: Math.floor((distance % (1000 * 60)) / 1000)
  };
};

onMounted(() => {
  updateCountdown();
  timerInterval = window.setInterval(() => {
    now.value = Date.now();
    updateCountdown();
  }, 1000);
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-32">
    <!-- --- Large Interactive Hero --- -->
    <section class="relative min-h-[80vh] flex items-center pt-28 pb-16 overflow-hidden bg-primary">
      <div class="absolute inset-0 z-0">
        <template v-if="heroEvent?.image">
          <ParallaxSection :offset="60" class="w-full h-full">
            <img 
              :src="heroEvent.image" 
              class="w-full h-full object-cover opacity-35" 
              alt="Next upcoming event" 
              loading="lazy"
            />
          </ParallaxSection>
        </template>
        <div v-else class="absolute inset-0 bg-gradient-to-br from-primary-dark via-primary to-primary-light opacity-90" />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/95 via-primary/30 to-primary/95" />
      </div>

      <div class="container-custom relative z-10 w-full">
        <template v-if="heroEvent">
          <div class="flex flex-col gap-12 xl:flex-row xl:items-start xl:justify-between xl:gap-20">
            <div class="flex-1 min-w-0 space-y-6 text-center sm:text-left max-w-3xl relative z-10">
              <ScrollReveal direction="up">
                <div class="inline-flex items-center gap-2.5 px-4.5 py-2 bg-white/10 backdrop-blur border border-white/15 rounded-full text-accent-gold text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.2em] mb-2 mx-auto sm:mx-0">
                  <Bell :size="14" class="animate-bounce" /> Next Upcoming Event
                </div>
              </ScrollReveal>

              <ScrollReveal :delay="0.15" width="100%">
                <h1 class="hero-event-title text-3xl sm:text-4xl md:text-5xl xl:text-6xl font-display font-black text-white leading-tight tracking-tight break-words">
                  {{ heroEvent.title }}
                </h1>
              </ScrollReveal>

              <ScrollReveal :delay="0.25">
                <p class="text-base sm:text-lg text-white/70 max-w-lg leading-relaxed mx-auto sm:mx-0 font-light">
                  {{ textPreview(heroEvent.description, 280) }}
                </p>
              </ScrollReveal>

              <ScrollReveal :delay="0.35" width="100%">
                <div class="flex flex-wrap justify-center sm:justify-start gap-6 items-center pt-2">
                  <div class="flex items-center gap-3.5 text-white text-left">
                    <div class="p-3 bg-white/10 rounded-[1.25rem] border border-white/10 shrink-0">
                      <CalendarDays :size="18" class="text-accent-gold" />
                    </div>
                    <div>
                      <div class="text-[9px] uppercase tracking-widest text-white/45 font-bold">Date</div>
                      <div class="text-sm sm:text-base font-extrabold">{{ heroEvent.date }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3.5 text-white text-left">
                    <div class="p-3 bg-white/10 rounded-[1.25rem] border border-white/10 shrink-0">
                      <Clock :size="18" class="text-accent-gold" />
                    </div>
                    <div>
                      <div class="text-[9px] uppercase tracking-widest text-white/45 font-bold">Time</div>
                      <div class="text-sm sm:text-base font-extrabold">{{ heroEvent.time }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3.5 text-white text-left">
                    <div class="p-3 bg-white/10 rounded-[1.25rem] border border-white/10 shrink-0">
                      <MapPin :size="18" class="text-accent-gold" />
                    </div>
                    <div>
                      <div class="text-[9px] uppercase tracking-widest text-white/45 font-bold">Location</div>
                      <div class="text-sm sm:text-base font-extrabold">{{ heroEvent.location }}</div>
                    </div>
                  </div>
                </div>
              </ScrollReveal>

              <ScrollReveal :delay="0.45" width="100%">
                <div class="pt-4 max-w-xl">
                  <div class="flex items-start gap-3 px-5 py-4 bg-white/10 backdrop-blur border border-white/15 rounded-2xl text-white/80 text-sm leading-relaxed">
                    <Info :size="18" class="text-accent-gold shrink-0 mt-0.5" />
                    <p>{{ REGISTRATION_NOTICE }}</p>
                  </div>
                </div>
              </ScrollReveal>
            </div>

            <aside class="w-full max-w-sm mx-auto sm:max-w-md xl:mx-0 xl:w-80 xl:flex-shrink-0 xl:pt-2">
              <div class="bg-white/5 backdrop-blur-md border border-white/15 p-8 xl:p-10 rounded-[2rem] xl:rounded-[2.5rem] text-center space-y-8 shadow-2xl">
                <h3 class="text-white text-[10px] uppercase tracking-[0.25em] font-extrabold font-sans">Starting In</h3>

                <div class="grid grid-cols-4 gap-3">
                  <div v-for="[unit, val] in Object.entries(timeLeft)" :key="unit" class="flex flex-col items-center min-w-0">
                    <span class="text-2xl xl:text-3xl font-display font-extrabold text-accent-gold tabular-nums">{{ val.toString().padStart(2, '0') }}</span>
                    <span class="text-[9px] uppercase tracking-[0.2em] text-white/55 mt-1 font-bold">{{ unit }}</span>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </template>

        <div v-else class="lg:col-span-2 space-y-6 text-center sm:text-left">
          <ScrollReveal direction="right">
            <div class="inline-flex items-center gap-2.5 px-4.5 py-2 bg-white/10 backdrop-blur border border-white/15 rounded-full text-accent-gold text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.2em] mb-2 mx-auto sm:mx-0">
              <CalendarDays :size="14" /> Events Calendar
            </div>
          </ScrollReveal>
          <ScrollReveal :delay="0.2">
            <h1 class="text-4xl sm:text-6xl md:text-7xl lg:text-8xl font-display font-black text-white leading-[0.95] tracking-tight">
              Community <span class="italic font-serif text-accent-gold">Events</span> at SFU
            </h1>
          </ScrollReveal>
          <ScrollReveal :delay="0.3">
            <p class="text-base sm:text-lg text-white/70 max-w-lg leading-relaxed mx-auto sm:mx-0 font-light">
              Browse lectures, social gatherings, workshops, and Jumu'ah programs organized by the MSA across all campuses.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- --- Navigation & Control Bar --- -->
    <div class="sticky top-20 z-40 bg-neutral-background/95 backdrop-blur-md border-b border-neutral-ivory/60">
      <div class="container-custom py-5 flex flex-col md:flex-row gap-5 md:items-center justify-between">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1 md:pb-0">
          <button 
            v-for="cat in CATEGORIES"
            :key="cat"
            @click="selectedCategory = cat"
            :class="[
              'flex-shrink-0 px-5.5 py-3 rounded-full text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer',
              selectedCategory === cat 
                ? 'bg-primary text-white' 
                : 'bg-white text-neutral-black/55 hover:bg-primary/5 border border-neutral-ivory'
            ]"
          >
            {{ cat }}
          </button>
        </div>

        <div class="flex items-center gap-4">
          <div class="relative flex-grow md:w-64">
            <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/30" :size="16" />
            <input 
              type="text" 
              placeholder="Search events..."
              v-model="searchQuery"
              class="w-full pl-12 pr-4 py-3 bg-white border border-neutral-ivory rounded-2xl text-xs focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all font-sans text-neutral-black"
            />
          </div>
          <div class="hidden sm:flex bg-white rounded-2xl p-1 border border-neutral-ivory">
            <button 
              @click="viewMode = 'grid'"
              :class="[
                'p-2 rounded-xl transition-all cursor-pointer',
                viewMode === 'grid' ? 'bg-primary text-white' : 'text-neutral-black/40 hover:text-primary'
              ]"
            >
              <LayoutGrid :size="16" />
            </button>
            <button 
              @click="viewMode = 'list'"
              :class="[
                'p-2 rounded-xl transition-all cursor-pointer',
                viewMode === 'list' ? 'bg-primary text-white' : 'text-neutral-black/40 hover:text-primary'
              ]"
            >
              <List :size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- --- Event Grid/List --- -->
    <section class="container-custom py-16">
      <div class="mb-10">
        <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">
          Upcoming Events
        </h2>
        <p class="text-neutral-black/50 text-sm mt-2 font-light">
          All published events that haven't ended yet
        </p>
      </div>
      <div :class="viewMode === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8' : 'space-y-6'">
        <div 
          v-for="event in filteredEvents" 
          :key="event.id"
          :class="[
            'group transition-all duration-300',
            viewMode === 'grid' 
              ? 'premium-card flex flex-col h-full bg-white border border-neutral-ivory shadow-soft hover:shadow-premium' 
              : 'bg-white p-6 rounded-3xl border border-neutral-ivory flex flex-col md:flex-row gap-8 items-center hover:shadow-premium'
          ]"
        >
          <div :class="[viewMode === 'grid' ? 'aspect-[16/10] w-full' : 'w-full md:w-64 h-48 rounded-[2rem]', 'relative overflow-hidden flex-shrink-0 bg-primary/5']">
            <img :src="event.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" :alt="event.title" />
            <div class="absolute top-5 left-5 flex flex-wrap gap-2">
              <div class="px-3.5 py-1.5 glass rounded-full text-[9px] font-extrabold uppercase tracking-widest text-primary border border-white/40">
                {{ event.category }}
              </div>
            </div>
          </div>

          <div :class="['p-6 sm:p-8 flex flex-col', viewMode === 'grid' ? 'flex-grow' : 'flex-grow md:py-4']">
            <div class="space-y-3 mb-6">
              <div class="flex items-center gap-5">
                <div class="flex items-center gap-1.5 text-primary text-[9px] font-extrabold uppercase tracking-widest">
                  <Calendar :size="12" /> {{ event.date }}
                </div>
              </div>
              <h3 class="text-xl sm:text-2xl font-extrabold text-neutral-black group-hover:text-primary transition-colors duration-300">
                {{ event.title }}
              </h3>
              <p class="text-neutral-black/55 text-sm leading-relaxed line-clamp-3 font-light font-sans">
                {{ textPreview(event.description, 220) }}
              </p>
            </div>

            <div class="mt-auto pt-5 border-t border-neutral-ivory/60 space-y-4">
              <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-neutral-black/45 text-[10px] font-medium font-sans">
                  <Clock :size="12" class="text-secondary" /> {{ event.time }}
                </div>
                <div class="flex items-center gap-1.5 text-neutral-black/45 text-[10px] font-medium font-sans">
                  <MapPin :size="12" class="text-secondary" /> {{ event.location }}
                </div>
              </div>
              <p class="flex items-start gap-2 text-[11px] text-neutral-black/50 leading-relaxed">
                <Info :size="14" class="text-primary shrink-0 mt-0.5" />
                {{ REGISTRATION_NOTICE }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="filteredEvents.length === 0" class="py-24 text-center space-y-6">
        <div class="bg-white inline-block p-12 rounded-3xl shadow-soft border border-neutral-ivory">
          <Search :size="44" class="text-secondary mx-auto mb-5 opacity-40 animate-pulse" />
          <h2 class="text-2xl font-display font-extrabold text-primary">No events found</h2>
          <p class="text-neutral-black/45 text-sm">Try adjusting your filters or search keywords.</p>
          <button 
            @click="selectedCategory = 'All'; searchQuery = '';"
            class="mt-6 text-primary font-bold uppercase tracking-widest text-[10px] hover:underline cursor-pointer"
          >
            Clear all filters
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.hero-event-title {
  color: #ffffff;
  font-weight: 800;
  font-size: 1.875rem;
  line-height: 1.15;
}

@media (min-width: 640px) {
  .hero-event-title {
    font-size: 2.25rem;
  }
}

@media (min-width: 768px) {
  .hero-event-title {
    font-size: 3rem;
  }
}

@media (min-width: 1280px) {
  .hero-event-title {
    font-size: 3.75rem;
  }
}
</style>
