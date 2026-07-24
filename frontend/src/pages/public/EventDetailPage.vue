<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import {
  ArrowLeft,
  Calendar,
  CalendarDays,
  CheckCircle2,
  Clock,
  MapPin,
  Minus,
  Plus,
  Users,
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import { useSeo } from '@/composables/useSeo';
import websiteService, {
  type EventItem,
  type EventRegistrationResult,
  type EventRsvpAttendee,
} from '@/services/website/websiteService';
import { textPreview } from '@/utils/html';

const route = useRoute();
const router = useRouter();

const event = ref<EventItem | null>(null);
const isLoading = ref(true);
const loadError = ref('');
const isSubmitting = ref(false);
const submitError = ref('');
const successMessage = ref('');
const createdRegistrations = ref<EventRegistrationResult[]>([]);

const emptyAttendee = (): EventRsvpAttendee => ({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
});

const attendeeCount = ref(1);
const attendees = ref<EventRsvpAttendee[]>([emptyAttendee()]);

const eventId = computed(() => String(route.params.id || ''));

const registrationClosed = computed(() => {
  if (!event.value?.registrationDeadline) return false;
  const deadline = new Date(`${event.value.registrationDeadline}T23:59:59`);
  return !Number.isNaN(deadline.getTime()) && deadline.getTime() < Date.now();
});

const canRegister = computed(() => {
  if (!event.value) return false;
  if (registrationClosed.value) return false;
  return event.value.spotsLeft > 0;
});

const maxPartySize = computed(() => {
  if (!event.value) return 1;
  return Math.max(1, Math.min(10, event.value.spotsLeft || 1));
});

useSeo(() => ({
  title: event.value ? `${event.value.title} | SFU MSA Events` : 'Event Details | SFU MSA',
  description: event.value
    ? textPreview(event.value.description, 160)
    : 'View event details and register with SFU MSA.',
}));

watch(attendeeCount, (count) => {
  const next = Math.max(1, Math.min(maxPartySize.value, count));
  if (next !== count) {
    attendeeCount.value = next;
    return;
  }

  while (attendees.value.length < next) {
    attendees.value.push(emptyAttendee());
  }
  while (attendees.value.length > next) {
    attendees.value.pop();
  }
});

watch(maxPartySize, (max) => {
  if (attendeeCount.value > max) {
    attendeeCount.value = max;
  }
});

async function loadEvent() {
  isLoading.value = true;
  loadError.value = '';
  try {
    event.value = await websiteService.getEvent(eventId.value);
  } catch (err: any) {
    loadError.value = err?.response?.data?.message || err?.message || 'Unable to load this event.';
    event.value = null;
  } finally {
    isLoading.value = false;
  }
}

function adjustCount(delta: number) {
  attendeeCount.value = Math.max(1, Math.min(maxPartySize.value, attendeeCount.value + delta));
}

async function handleRegister() {
  if (!event.value || !canRegister.value) return;

  submitError.value = '';
  successMessage.value = '';
  createdRegistrations.value = [];
  isSubmitting.value = true;

  try {
    const payload = {
      attendees: attendees.value.slice(0, attendeeCount.value).map((person) => ({
        firstName: person.firstName.trim(),
        lastName: person.lastName.trim(),
        email: person.email.trim(),
        phone: person.phone.trim(),
      })),
    };

    const result = await websiteService.submitEventRsvp(event.value.id, payload);
    successMessage.value = result.message;
    createdRegistrations.value = result.registrations ?? [];

    if (typeof result.spotsLeft === 'number') {
      event.value.spotsLeft = result.spotsLeft;
    }

    if (result.registrationId) {
      websiteService.saveLocalEventRegistration({
        eventId: event.value.id,
        registrationId: result.registrationId,
        status: 'registered',
        registeredAt: new Date().toISOString(),
      });
    }

    attendees.value = [emptyAttendee()];
    attendeeCount.value = 1;
  } catch (err: any) {
    submitError.value =
      err?.response?.data?.message ||
      err?.message ||
      'Registration could not be completed. Please try again.';
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(loadEvent);
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <section class="relative overflow-hidden bg-primary pt-28 pb-16">
      <div class="absolute inset-0">
        <img
          v-if="event?.image"
          :src="event.image"
          :alt="event.title"
          class="h-full w-full object-cover opacity-30"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/95 via-primary/55 to-primary" />
      </div>

      <div class="container-custom relative z-10">
        <button
          type="button"
          class="mb-8 inline-flex items-center gap-2 text-white/70 text-xs font-bold uppercase tracking-widest hover:text-white transition-colors cursor-pointer"
          @click="router.push({ name: 'events' })"
        >
          <ArrowLeft :size="14" /> Back to events
        </button>

        <div v-if="isLoading" class="text-white/70 text-sm">Loading event details...</div>
        <div v-else-if="loadError" class="bg-white/10 border border-white/15 rounded-3xl p-8 text-white max-w-xl">
          <p class="font-semibold mb-4">{{ loadError }}</p>
          <RouterLink to="/events" class="text-accent-gold text-sm font-bold uppercase tracking-widest">
            Return to events
          </RouterLink>
        </div>
        <div v-else-if="event" class="max-w-3xl space-y-6">
          <ScrollReveal>
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/15 rounded-full text-accent-gold text-[10px] font-extrabold uppercase tracking-[0.2em]">
              {{ event.category }}
            </div>
          </ScrollReveal>
          <ScrollReveal :delay="0.1">
            <h1 class="text-3xl sm:text-5xl font-display font-black text-white leading-tight">
              {{ event.title }}
            </h1>
          </ScrollReveal>
          <ScrollReveal :delay="0.2">
            <div class="flex flex-wrap gap-5 text-white/85">
              <div class="flex items-center gap-2 text-sm">
                <CalendarDays :size="16" class="text-accent-gold" /> {{ event.date }}
              </div>
              <div class="flex items-center gap-2 text-sm">
                <Clock :size="16" class="text-accent-gold" /> {{ event.time }}
              </div>
              <div class="flex items-center gap-2 text-sm">
                <MapPin :size="16" class="text-accent-gold" /> {{ event.location }}
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <section v-if="event" class="container-custom py-12 grid grid-cols-1 lg:grid-cols-5 gap-10">
      <div class="lg:col-span-3 space-y-8">
        <div class="bg-white border border-neutral-ivory rounded-[2rem] p-8 shadow-soft">
          <h2 class="text-2xl font-display font-extrabold text-primary mb-4">About this event</h2>
          <div class="prose prose-sm max-w-none text-neutral-black/70 leading-relaxed whitespace-pre-wrap" v-html="event.description" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white border border-neutral-ivory rounded-3xl p-5">
            <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold mb-1">Date</div>
            <div class="flex items-center gap-2 text-sm font-semibold text-neutral-black">
              <Calendar :size="14" class="text-primary" /> {{ event.date }}
            </div>
          </div>
          <div class="bg-white border border-neutral-ivory rounded-3xl p-5">
            <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold mb-1">Spots left</div>
            <div class="flex items-center gap-2 text-sm font-semibold text-neutral-black">
              <Users :size="14" class="text-primary" /> {{ event.spotsLeft }}
            </div>
          </div>
          <div class="bg-white border border-neutral-ivory rounded-3xl p-5">
            <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold mb-1">Register by</div>
            <div class="text-sm font-semibold text-neutral-black">{{ event.registrationDeadline }}</div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-2">
        <div class="bg-white border border-neutral-ivory rounded-[2rem] p-7 sm:p-8 shadow-premium sticky top-28">
          <h2 class="text-xl font-display font-extrabold text-primary mb-2">Register</h2>
          <p class="text-sm text-neutral-black/55 mb-6">
            Choose how many people are coming. Each person gets their own QR code by email.
          </p>

          <div v-if="successMessage" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 space-y-3">
            <div class="flex items-start gap-2">
              <CheckCircle2 :size="18" class="shrink-0 mt-0.5" />
              <p>{{ successMessage }}</p>
            </div>
            <ul v-if="createdRegistrations.length" class="space-y-1 pl-6 list-disc text-emerald-800/80">
              <li v-for="item in createdRegistrations" :key="item.registrationId">
                {{ item.name }} — {{ item.email }}
              </li>
            </ul>
          </div>

          <div v-if="!canRegister" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p v-if="registrationClosed">Registration for this event has closed.</p>
            <p v-else>This event is full. No spots remain.</p>
          </div>

          <form v-else class="space-y-6" @submit.prevent="handleRegister">
            <div>
              <label class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/45 block mb-3">
                Number of people
              </label>
              <div class="flex items-center gap-4">
                <button
                  type="button"
                  class="h-11 w-11 rounded-2xl border border-neutral-ivory flex items-center justify-center hover:bg-primary/5 cursor-pointer disabled:opacity-40"
                  :disabled="attendeeCount <= 1"
                  @click="adjustCount(-1)"
                >
                  <Minus :size="16" />
                </button>
                <div class="text-2xl font-display font-extrabold text-primary tabular-nums min-w-10 text-center">
                  {{ attendeeCount }}
                </div>
                <button
                  type="button"
                  class="h-11 w-11 rounded-2xl border border-neutral-ivory flex items-center justify-center hover:bg-primary/5 cursor-pointer disabled:opacity-40"
                  :disabled="attendeeCount >= maxPartySize"
                  @click="adjustCount(1)"
                >
                  <Plus :size="16" />
                </button>
              </div>
              <p class="text-[11px] text-neutral-black/40 mt-2">Up to {{ maxPartySize }} based on remaining spots.</p>
            </div>

            <div
              v-for="(person, index) in attendees.slice(0, attendeeCount)"
              :key="index"
              class="rounded-2xl border border-neutral-ivory p-4 space-y-3"
            >
              <h3 class="text-xs font-extrabold uppercase tracking-widest text-primary">
                Person {{ index + 1 }}
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-black/40 block mb-1.5">First name</label>
                  <input
                    v-model="person.firstName"
                    required
                    type="text"
                    class="w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
                  />
                </div>
                <div>
                  <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-black/40 block mb-1.5">Last name</label>
                  <input
                    v-model="person.lastName"
                    required
                    type="text"
                    class="w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
                  />
                </div>
              </div>
              <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-black/40 block mb-1.5">Email</label>
                <input
                  v-model="person.email"
                  required
                  type="email"
                  class="w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
                />
              </div>
              <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-black/40 block mb-1.5">Phone</label>
                <input
                  v-model="person.phone"
                  required
                  type="tel"
                  class="w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary"
                />
              </div>
            </div>

            <p v-if="submitError" class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
              {{ submitError }}
            </p>

            <button
              type="submit"
              class="w-full rounded-2xl bg-primary text-white py-3.5 text-xs font-extrabold uppercase tracking-widest hover:bg-primary-dark transition-colors cursor-pointer disabled:opacity-60"
              :disabled="isSubmitting"
            >
              {{ isSubmitting ? 'Registering...' : `Register ${attendeeCount} ${attendeeCount === 1 ? 'person' : 'people'}` }}
            </button>
          </form>
        </div>
      </div>
    </section>
  </div>
</template>
