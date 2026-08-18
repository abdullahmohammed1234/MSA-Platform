<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowRight,
  Bell,
  Calendar,
  CalendarDays,
  Clock,
  MapPin,
  Search,
  Users,
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import ParallaxSection from '@/components/shared/ParallaxSection.vue';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import { EMS_PUBLIC_CALENDAR_PATH, emsPublicEventPath } from '@/constants/ems';
import { resolvePublicImagePath } from '@/constants/publicAssets';
import type { PublicCategory, PublicEvent } from '@/types/ems/public';
import pendingCheckoutStorage, { type StoredPendingCheckout } from '@/services/ems/pendingCheckoutStorage';

useSeo({
  title: 'Events | SFU MSA',
  description: 'Browse upcoming SFU MSA community events and register for free.',
});

const router = useRouter();
const { formatDate, formatTime } = useEventFormatting();

const events = ref<PublicEvent[]>([]);
const categories = ref<PublicCategory[]>([]);
const loading = ref(true);
const error = ref('');
const search = ref('');
const selectedCategory = ref('all');
const range = ref<'upcoming' | 'past' | 'all'>('upcoming');
const registrationFilter = ref<'any' | 'open' | 'closed'>('any');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const savedCheckouts = ref<StoredPendingCheckout[]>([]);

const categoryChips = computed(() => [
  { label: 'All', value: 'all' },
  ...categories.value.map((category) => ({ label: category.name, value: category.slug })),
]);

async function loadCategories() {
  try {
    categories.value = await publicEventsService.listCategories();
  } catch {
    categories.value = [];
  }
}

async function loadEvents() {
  loading.value = true;
  error.value = '';

  try {
    const result = await publicEventsService.listEvents({
      search: search.value || undefined,
      category_slug: selectedCategory.value === 'all' ? undefined : selectedCategory.value,
      upcoming: range.value === 'upcoming' ? true : undefined,
      past: range.value === 'past' ? true : undefined,
      registration_open: registrationFilter.value === 'open' ? true : undefined,
      registration_closed: registrationFilter.value === 'closed' ? true : undefined,
      page: page.value,
      per_page: 12,
      sort_by: 'start_at',
      sort_direction: range.value === 'past' ? 'desc' : 'asc',
    });

    events.value = result.items;
    lastPage.value = result.pagination.last_page;
    total.value = result.pagination.total;
  } catch (err) {
    error.value = err instanceof EmsApiError ? err.message : 'Unable to load events.';
    events.value = [];
  } finally {
    loading.value = false;
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    void loadEvents();
  }, 300);
});

watch([selectedCategory, range, registrationFilter], () => {
  page.value = 1;
  void loadEvents();
});

// --- Hero: next upcoming event with live countdown -------------------------
// Candidates are fetched independently of the list filters; when the current
// hero event's day ends, the computed automatically advances to the next one.

const heroCandidates = ref<PublicEvent[]>([]);
const now = ref(Date.now());
let clockInterval: number | null = null;

function eventStartMs(event: PublicEvent): number {
  if (!event.start_at) return Number.MAX_SAFE_INTEGER;
  const parsed = new Date(event.start_at).getTime();
  return Number.isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed;
}

/** The hero stays until the event is over (end time, or end of its start day). */
function eventEndMs(event: PublicEvent): number {
  if (event.end_at) {
    const parsed = new Date(event.end_at).getTime();
    if (!Number.isNaN(parsed)) return parsed;
  }
  const start = eventStartMs(event);
  if (start === Number.MAX_SAFE_INTEGER) return 0;
  const endOfDay = new Date(start);
  endOfDay.setHours(23, 59, 59, 999);
  return endOfDay.getTime();
}

const heroEvent = computed<PublicEvent | null>(() =>
  heroCandidates.value.find((event) => eventEndMs(event) > now.value) ?? null
);

const timeLeft = computed(() => {
  if (!heroEvent.value) return { days: 0, hours: 0, minutes: 0, seconds: 0 };

  const distance = Math.max(0, eventStartMs(heroEvent.value) - now.value);

  return {
    days: Math.floor(distance / 86_400_000),
    hours: Math.floor((distance % 86_400_000) / 3_600_000),
    minutes: Math.floor((distance % 3_600_000) / 60_000),
    seconds: Math.floor((distance % 60_000) / 1000),
  };
});

const heroStarted = computed(() =>
  Boolean(heroEvent.value && eventStartMs(heroEvent.value) <= now.value)
);

async function loadHeroCandidates() {
  try {
    const result = await publicEventsService.listEvents({
      upcoming: true,
      per_page: 5,
      sort_by: 'start_at',
      sort_direction: 'asc',
    });
    heroCandidates.value = result.items;
  } catch {
    heroCandidates.value = [];
  }
}

onMounted(async () => {
  clockInterval = window.setInterval(() => {
    now.value = Date.now();
  }, 1000);

  savedCheckouts.value = pendingCheckoutStorage.list();
  await Promise.all([loadCategories(), loadHeroCandidates()]);
  await loadEvents();
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
});

function openEvent(slug: string) {
  router.push({ name: 'ems-public-event', params: { slug } });
}

function capacityLabel(event: PublicEvent): string {
  if (event.capacity === null) return 'Open seating';
  if (event.is_full) return 'Full';
  if (event.remaining_capacity !== null) {
    return `${event.remaining_capacity} of ${event.capacity} left`;
  }
  return `${event.capacity} capacity`;
}
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <!-- --- Large Interactive Hero: next upcoming event + countdown --- -->
    <section class="relative min-h-[80vh] flex items-center pt-28 pb-16 overflow-hidden bg-primary">
      <div class="absolute inset-0 z-0">
        <template v-if="heroEvent?.banner_url">
          <ParallaxSection :offset="60" class="w-full h-full">
            <img
              :src="resolvePublicImagePath(heroEvent.banner_url)"
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
                  <Bell :size="14" class="animate-bounce" />
                  {{ heroStarted ? 'Happening Now' : 'Next Upcoming Event' }}
                </div>
              </ScrollReveal>

              <ScrollReveal :delay="0.15" width="100%">
                <h1 class="text-3xl sm:text-4xl md:text-5xl xl:text-6xl font-display font-black text-white leading-tight tracking-tight break-words">
                  {{ heroEvent.name }}
                </h1>
              </ScrollReveal>

              <ScrollReveal v-if="heroEvent.short_description" :delay="0.25">
                <p class="text-base sm:text-lg text-white/70 max-w-lg leading-relaxed mx-auto sm:mx-0 font-light">
                  {{ heroEvent.short_description }}
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
                      <div class="text-sm sm:text-base font-extrabold">{{ formatDate(heroEvent.start_at) }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3.5 text-white text-left">
                    <div class="p-3 bg-white/10 rounded-[1.25rem] border border-white/10 shrink-0">
                      <Clock :size="18" class="text-accent-gold" />
                    </div>
                    <div>
                      <div class="text-[9px] uppercase tracking-widest text-white/45 font-bold">Time</div>
                      <div class="text-sm sm:text-base font-extrabold">{{ formatTime(heroEvent.start_at) }}</div>
                    </div>
                  </div>
                  <div v-if="heroEvent.location" class="flex items-center gap-3.5 text-white text-left">
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
                <div class="pt-4 flex flex-wrap justify-center sm:justify-start gap-3">
                  <button
                    v-if="heroEvent.is_accepting_registrations && !heroEvent.is_full"
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-3.5 bg-accent-gold text-primary rounded-2xl text-xs font-extrabold uppercase tracking-widest hover:brightness-105 transition-all cursor-pointer"
                    @click="openEvent(heroEvent.slug)"
                  >
                    View & Register
                    <ArrowRight :size="14" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-3.5 bg-white/10 border border-white/20 text-white rounded-2xl text-xs font-extrabold uppercase tracking-widest hover:bg-white/15 transition-all cursor-pointer"
                    @click="openEvent(heroEvent.slug)"
                  >
                    Event details
                  </button>
                </div>
              </ScrollReveal>
            </div>

            <aside class="w-full max-w-sm mx-auto sm:max-w-md xl:mx-0 xl:w-80 xl:flex-shrink-0 xl:pt-2">
              <div class="bg-white/5 backdrop-blur-md border border-white/15 p-8 xl:p-10 rounded-[2rem] xl:rounded-[2.5rem] text-center space-y-8 shadow-2xl">
                <h3 class="text-white text-[10px] uppercase tracking-[0.25em] font-extrabold font-sans">
                  {{ heroStarted ? 'Started' : 'Starting In' }}
                </h3>

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

        <div v-else class="space-y-6 text-center sm:text-left">
          <ScrollReveal direction="up">
            <div class="inline-flex items-center gap-2.5 px-4.5 py-2 bg-white/10 backdrop-blur border border-white/15 rounded-full text-accent-gold text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.2em] mb-2 mx-auto sm:mx-0">
              <CalendarDays :size="14" /> Events Calendar
            </div>
          </ScrollReveal>
          <ScrollReveal :delay="0.2">
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-display font-black text-white leading-[0.95] tracking-tight">
              Community <span class="italic font-serif text-accent-gold">Events</span> at SFU
            </h1>
          </ScrollReveal>
          <ScrollReveal :delay="0.3">
            <p class="text-base sm:text-lg text-white/70 max-w-lg leading-relaxed mx-auto sm:mx-0 font-light">
              Discover lectures, socials, fundraisers, and community gatherings. Register for free events and receive your ticket instantly.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <div class="sticky top-20 z-40 bg-neutral-background/95 backdrop-blur-md border-b border-neutral-ivory/60">
      <div class="container-custom py-5 flex items-center gap-3 sm:gap-4">
        <div
          class="min-w-0 flex-1 flex items-center gap-2 overflow-x-auto overflow-y-hidden [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
        >
          <button
            v-for="chip in categoryChips"
            :key="chip.value"
            type="button"
            class="flex-shrink-0 h-11 px-5 rounded-full text-[10px] font-extrabold uppercase tracking-widest transition cursor-pointer"
            :class="selectedCategory === chip.value
              ? 'bg-primary text-white'
              : 'bg-white text-neutral-black/55 border border-neutral-ivory hover:bg-primary/5'"
            @click="selectedCategory = chip.value"
          >
            {{ chip.label }}
          </button>
        </div>

        <div class="relative w-44 sm:w-56 md:w-64 shrink-0">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-black/30 pointer-events-none" :size="16" />
          <input
            v-model="search"
            type="search"
            placeholder="Search events..."
            class="w-full h-11 pl-11 pr-4 bg-white border border-neutral-ivory rounded-2xl text-xs focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
            aria-label="Search events"
          />
        </div>

        <RouterLink
          :to="EMS_PUBLIC_CALENDAR_PATH"
          class="inline-flex items-center gap-2 h-11 px-5 shrink-0 rounded-2xl bg-primary text-white text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary transition"
        >
          <CalendarDays :size="14" />
          <span class="hidden sm:inline">Calendar view</span>
        </RouterLink>
      </div>
    </div>

    <section class="container-custom pt-10">
      <div
        v-if="savedCheckouts.length"
        class="mb-6 rounded-[1.75rem] border border-amber-200 bg-amber-50 p-5 sm:p-6"
      >
        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Saved payments</p>
        <p class="mt-1 text-sm text-amber-950/80">
          You have unfinished checkout{{ savedCheckouts.length > 1 ? 's' : '' }} on this device.
        </p>
        <div class="mt-3 flex flex-col gap-2">
          <RouterLink
            v-for="item in savedCheckouts"
            :key="item.order_uuid"
            :to="emsPublicEventPath(item.slug)"
            class="flex items-center justify-between gap-3 rounded-2xl bg-white/80 px-4 py-3 text-sm hover:bg-white"
          >
            <span class="font-semibold text-primary">{{ item.event_name }}</span>
            <span class="text-xs font-bold uppercase tracking-widest text-amber-800">Complete payment</span>
          </RouterLink>
        </div>
      </div>
      <div v-if="loading" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true">
        <div
          v-for="n in 6"
          :key="n"
          class="h-72 rounded-[1.75rem] bg-white border border-neutral-ivory animate-pulse"
        />
      </div>

      <div
        v-else-if="error"
        class="rounded-[1.75rem] border border-red-200 bg-red-50 p-8 text-center"
        role="alert"
      >
        <p class="text-red-800 font-semibold">{{ error }}</p>
        <button
          type="button"
          class="mt-4 text-sm font-bold text-primary underline cursor-pointer"
          @click="loadEvents"
        >
          Try again
        </button>
      </div>

      <div
        v-else-if="events.length === 0"
        class="rounded-[1.75rem] border border-neutral-ivory bg-white p-12 text-center"
      >
        <Calendar class="mx-auto text-neutral-black/25 mb-4" :size="36" />
        <h2 class="font-display text-2xl font-bold text-neutral-black">No events found</h2>
        <p class="mt-2 text-neutral-black/55 text-sm max-w-md mx-auto">
          Try a different category, clear your search, or check past events.
        </p>
      </div>

      <div v-else class="space-y-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-neutral-black/40">
          {{ total }} event{{ total === 1 ? '' : 's' }}
        </p>

        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="event in events"
            :key="event.uuid"
            class="group flex flex-col overflow-hidden rounded-[1.75rem] border border-neutral-ivory bg-white hover:border-primary/20 hover:shadow-lg transition cursor-pointer"
            @click="openEvent(event.slug)"
          >
            <div class="relative h-40 bg-gradient-to-br from-primary to-primary-light overflow-hidden">
              <img
                v-if="event.banner_url"
                :src="resolvePublicImagePath(event.banner_url)"
                :alt="event.name"
                class="h-full w-full object-cover opacity-90 group-hover:scale-105 transition duration-500"
                loading="lazy"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-primary/80 to-transparent" />
              <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2">
                <span
                  v-if="event.category"
                  class="text-[10px] font-extrabold uppercase tracking-widest text-white/90 bg-white/15 backdrop-blur px-3 py-1 rounded-full"
                >
                  {{ event.category.name }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-accent-gold bg-primary/80 px-3 py-1 rounded-full">
                  {{ event.registration_label }}
                </span>
              </div>
            </div>

            <div class="flex flex-1 flex-col p-5 gap-3">
              <h2 class="font-display text-xl font-bold text-neutral-black leading-snug group-hover:text-primary transition">
                {{ event.name }}
              </h2>
              <p v-if="event.short_description" class="text-sm text-neutral-black/55 line-clamp-2">
                {{ event.short_description }}
              </p>

              <div class="mt-auto space-y-2 pt-2 text-xs text-neutral-black/60">
                <div class="flex items-center gap-2">
                  <Calendar :size="14" class="text-primary shrink-0" />
                  <span>{{ formatDate(event.start_at) }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <Clock :size="14" class="text-primary shrink-0" />
                  <span>{{ formatTime(event.start_at) }}</span>
                </div>
                <div v-if="event.location" class="flex items-center gap-2">
                  <MapPin :size="14" class="text-primary shrink-0" />
                  <span class="truncate">{{ event.location }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <Users :size="14" class="text-primary shrink-0" />
                  <span>{{ capacityLabel(event) }}</span>
                </div>
              </div>

              <button
                type="button"
                class="mt-3 inline-flex items-center justify-center gap-2 w-full py-3 rounded-2xl text-xs font-extrabold uppercase tracking-widest transition"
                :class="event.is_accepting_registrations && !event.is_full
                  ? 'bg-primary text-white hover:brightness-110'
                  : 'bg-neutral-ivory text-neutral-black/50'"
                @click.stop="openEvent(event.slug)"
              >
                {{ event.is_accepting_registrations && !event.is_full ? 'Register' : 'View details' }}
                <ArrowRight :size="14" />
              </button>
            </div>
          </article>
        </div>

        <div v-if="lastPage > 1" class="flex items-center justify-center gap-3 pt-4">
          <button
            type="button"
            class="px-4 py-2 rounded-xl border border-neutral-ivory text-xs font-bold disabled:opacity-40 cursor-pointer"
            :disabled="page <= 1"
            @click="page -= 1; loadEvents()"
          >
            Previous
          </button>
          <span class="text-xs text-neutral-black/50">Page {{ page }} of {{ lastPage }}</span>
          <button
            type="button"
            class="px-4 py-2 rounded-xl border border-neutral-ivory text-xs font-bold disabled:opacity-40 cursor-pointer"
            :disabled="page >= lastPage"
            @click="page += 1; loadEvents()"
          >
            Next
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
