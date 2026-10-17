<template>
  <div class="space-y-6">
    <!-- Back Navigation -->
    <div class="flex items-center justify-between">
      <router-link
        to="/app/events"
        class="inline-flex items-center gap-2 text-xs font-semibold text-neutral-600 hover:text-[#640c0e] transition-colors"
      >
        <ArrowLeft class="w-4 h-4" />
        <span>Back to Events Catalogue</span>
      </router-link>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          :is-loading="eventsStore.isLoadingDetail"
          @click="loadEvent"
          title="Reload event"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': eventsStore.isLoadingDetail }" />
          </template>
          <span>Refresh</span>
        </Button>
      </div>
    </div>

    <!-- Error State -->
    <ErrorState
      v-if="eventsStore.detailError"
      title="Event Not Found"
      :message="eventsStore.detailError"
      @retry="loadEvent"
    />

    <!-- Loading State -->
    <div
      v-else-if="eventsStore.isLoadingDetail || !event"
      class="py-24 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Loading event details...</p>
    </div>

    <!-- Event Detail Content -->
    <div v-else class="space-y-8">
      <!-- 1. Hero / Header Card -->
      <div class="premium-card bg-white border border-[#ebe8de] overflow-hidden">
        <!-- Banner if provided -->
        <div
          v-if="event.banner_url"
          class="w-full h-64 sm:h-80 bg-neutral-100 overflow-hidden relative"
        >
          <img
            :src="event.banner_url"
            :alt="event.name"
            class="w-full h-full object-cover"
          />
        </div>

        <div class="p-6 sm:p-8 space-y-4">
          <!-- Badges & Status -->
          <div class="flex items-center gap-2 flex-wrap">
            <Badge v-if="event.is_featured" variant="gold" size="sm">
              Featured Event
            </Badge>
            <Badge v-if="event.category?.name" variant="neutral" size="sm">
              {{ event.category.name }}
            </Badge>
            <Badge
              :variant="statusBadgeVariant"
              size="sm"
            >
              {{ event.status_label || event.status }}
            </Badge>
            <Badge v-if="event.is_full" variant="red" size="sm">
              {{ event.waitlist_enabled ? 'Waitlist Open' : 'Capacity Reached' }}
            </Badge>
          </div>

          <h1 class="text-2xl sm:text-3xl font-extrabold text-[#640c0e] leading-tight">
            {{ event.name }}
          </h1>

          <!-- Organizer -->
          <p v-if="event.organizer?.name" class="text-xs text-neutral-500">
            Organized by <strong class="text-neutral-700">{{ event.organizer.name }}</strong>
          </p>

          <!-- Key Meta Items (Date, Time, Location) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-3 border-t border-[#ebe8de]/70 text-xs">
            <!-- Date -->
            <div class="flex items-start gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-rose-50 text-[#b02e32] flex items-center justify-center shrink-0">
                <Calendar class="w-4 h-4" />
              </div>
              <div class="space-y-0.5">
                <span class="text-[10px] uppercase font-semibold text-neutral-400 block">Date</span>
                <span class="font-bold text-neutral-800 block">{{ formattedDate }}</span>
              </div>
            </div>

            <!-- Time -->
            <div class="flex items-start gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-rose-50 text-[#b02e32] flex items-center justify-center shrink-0">
                <Clock class="w-4 h-4" />
              </div>
              <div class="space-y-0.5">
                <span class="text-[10px] uppercase font-semibold text-neutral-400 block">Time</span>
                <span class="font-bold text-neutral-800 block">{{ formattedTime }}</span>
              </div>
            </div>

            <!-- Location -->
            <div class="flex items-start gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-rose-50 text-[#b02e32] flex items-center justify-center shrink-0">
                <MapPin class="w-4 h-4" />
              </div>
              <div class="space-y-0.5">
                <span class="text-[10px] uppercase font-semibold text-neutral-400 block">Venue</span>
                <span class="font-bold text-neutral-800 block">{{ event.location || 'Location TBA' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Two-Column Layout: Description & Registration Action -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Column: Description & Ticket Types -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Community Inclusivity Banner -->
          <div class="community-card p-4 sm:p-5 bg-gradient-to-r from-white via-white to-[#fffbf4] border border-[#ebe8de] flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-[#b02e32] flex items-center justify-center shrink-0">
              <HeartHandshake class="w-5 h-5" />
            </div>
            <div class="text-xs text-neutral-600 leading-relaxed">
              <span class="text-[#640c0e] font-bold block">Open Campus Atmosphere</span>
              All SFU students, converts, and seekers are welcome to attend this gathering. No prior background is needed.
            </div>
          </div>

          <!-- Description Card -->
          <Card title="About This Event">
            <div v-if="event.description || event.short_description" class="prose prose-sm max-w-none text-xs sm:text-sm text-neutral-700 leading-relaxed whitespace-pre-line">
              {{ event.description || event.short_description }}
            </div>
            <p v-else class="text-xs text-neutral-400 italic">
              No additional description provided for this event.
            </p>
          </Card>

          <!-- Ticket Types -->
          <Card
            v-if="event.ticket_types && event.ticket_types.length > 0"
            title="Ticket Types & Admission"
            subtitle="Verified admission tiers managed by EMS"
          >
            <div class="space-y-3">
              <div
                v-for="ticket in event.ticket_types"
                :key="ticket.uuid"
                class="p-4 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/50 flex items-center justify-between gap-4"
              >
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <h4 class="text-sm font-bold text-neutral-800">{{ ticket.name }}</h4>
                    <Badge v-if="ticket.is_sold_out" variant="red" size="sm">Sold Out</Badge>
                    <Badge v-else-if="ticket.is_free" variant="green" size="sm">Free</Badge>
                  </div>
                  <p v-if="ticket.description" class="text-xs text-neutral-500">{{ ticket.description }}</p>
                </div>

                <div class="text-right shrink-0">
                  <span class="text-sm font-bold text-[#640c0e]">
                    {{ ticket.is_free ? 'Free' : `$${ticket.price} ${ticket.currency}` }}
                  </span>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <!-- Right Column: Registration Card & Calendar Links -->
        <div class="space-y-6">
          <!-- Registration Box -->
          <div class="premium-card p-6 bg-white border border-[#ebe8de] space-y-5">
            <div class="space-y-1">
              <span class="text-[11px] uppercase tracking-wider font-semibold text-neutral-400 block">
                Registration Status
              </span>
              <div class="flex items-center justify-between">
                <span class="text-base font-bold text-[#640c0e]">
                  {{ registrationStatusText }}
                </span>
                <span v-if="event.remaining_capacity !== null && !event.is_full" class="text-xs font-semibold text-emerald-700">
                  {{ event.remaining_capacity }} spots left
                </span>
              </div>
            </div>

            <!-- RSVP Button -->
            <Button
              v-if="canRegister"
              variant="primary"
              size="lg"
              class="w-full justify-center shadow-sm"
              @click="isRsvpModalOpen = true"
            >
              <span>RSVP / Reserve Ticket</span>
              <template #right-icon>
                <ArrowRight class="w-4 h-4" />
              </template>
            </Button>

            <div v-else class="p-3 rounded-xl bg-neutral-100 text-neutral-600 text-xs text-center font-medium">
              {{ unavailableReason }}
            </div>

            <p class="text-[11px] text-neutral-400 text-center leading-relaxed">
              Registrations are free and verified against your active SFU student account.
            </p>
          </div>

          <!-- Add to Calendar Box -->
          <div class="premium-card p-5 bg-white border border-[#ebe8de] space-y-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-500 flex items-center gap-2">
              <CalendarPlus class="w-4 h-4 text-[#b02e32]" />
              <span>Add to Calendar</span>
            </h4>

            <div class="space-y-2">
              <!-- Google Calendar -->
              <a
                v-if="calendarLinks?.google && isValidExternalCalendarUrl(calendarLinks.google)"
                :href="calendarLinks.google"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-between p-2.5 rounded-xl border border-[#ebe8de] hover:bg-[#fffbf4] text-xs font-medium text-neutral-700 transition-colors"
              >
                <span>Google Calendar</span>
                <ExternalLink class="w-3.5 h-3.5 text-neutral-400" />
              </a>

              <!-- Outlook -->
              <a
                v-if="calendarLinks?.outlook && isValidExternalCalendarUrl(calendarLinks.outlook)"
                :href="calendarLinks.outlook"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-between p-2.5 rounded-xl border border-[#ebe8de] hover:bg-[#fffbf4] text-xs font-medium text-neutral-700 transition-colors"
              >
                <span>Outlook.com</span>
                <ExternalLink class="w-3.5 h-3.5 text-neutral-400" />
              </a>

              <!-- Yahoo -->
              <a
                v-if="calendarLinks?.yahoo && isValidExternalCalendarUrl(calendarLinks.yahoo)"
                :href="calendarLinks.yahoo"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-between p-2.5 rounded-xl border border-[#ebe8de] hover:bg-[#fffbf4] text-xs font-medium text-neutral-700 transition-colors"
              >
                <span>Yahoo Calendar</span>
                <ExternalLink class="w-3.5 h-3.5 text-neutral-400" />
              </a>

              <!-- Download iCalendar .ics Stream -->
              <a
                :href="icsDownloadUrl"
                download
                class="flex items-center justify-between p-2.5 rounded-xl border border-[#ebe8de] hover:bg-[#fffbf4] text-xs font-medium text-neutral-700 transition-colors"
              >
                <span>Download .ics File (Apple Calendar)</span>
                <Download class="w-3.5 h-3.5 text-neutral-400" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RSVP Modal Dialog -->
    <RsvpModal
      v-if="event"
      v-model="isRsvpModalOpen"
      :event="event"
      @success="handleRsvpSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useEventsStore } from '@/stores/events';
import { formatEventDate, formatEventTime } from '@/utils/formatters';
import { isValidExternalCalendarUrl } from '@/utils/security';
import type { PublicEventDetail, Registration } from '@/types/ems';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import RsvpModal from '@/components/events/RsvpModal.vue';
import {
  Calendar,
  Clock,
  MapPin,
  ArrowLeft,
  ArrowRight,
  CalendarPlus,
  ExternalLink,
  Download,
  RefreshCw,
  HeartHandshake,
} from 'lucide-vue-next';

const route = useRoute();
const eventsStore = useEventsStore();

const isRsvpModalOpen = ref<boolean>(false);

const event = computed<PublicEventDetail | null>(() => eventsStore.currentEvent);
const calendarLinks = computed(() => eventsStore.calendarLinks);

const formattedDate = computed(() => {
  return formatEventDate(event.value?.start_at, event.value?.timezone);
});

const formattedTime = computed(() => {
  return formatEventTime(event.value?.start_at, event.value?.end_at, event.value?.timezone);
});

const icsDownloadUrl = computed(() => {
  const slug = route.params.slug as string;
  return eventsStore.getIcsUrl(slug);
});

const canRegister = computed(() => {
  if (!event.value) return false;
  if (event.value.status === 'cancelled') return false;
  return event.value.is_accepting_registrations;
});

const registrationStatusText = computed(() => {
  if (!event.value) return '';
  if (event.value.status === 'cancelled') return 'Event Cancelled';
  if (event.value.is_sold_out) return 'Sold Out';
  if (event.value.is_full) return event.value.waitlist_enabled ? 'Waitlist Open' : 'Capacity Full';
  if (event.value.is_accepting_registrations) return 'Open for RSVP';
  return 'Registration Closed';
});

const unavailableReason = computed(() => {
  if (!event.value) return 'Unavailable';
  if (event.value.status === 'cancelled') return 'This event has been cancelled by the organizers.';
  if (event.value.is_sold_out) return 'All available tickets have been claimed.';
  if (event.value.is_full && !event.value.waitlist_enabled) return 'This event has reached full capacity.';
  return 'Registration is not currently open for this event.';
});

const statusBadgeVariant = computed<'green' | 'red' | 'gold' | 'neutral'>(() => {
  if (!event.value) return 'neutral';
  if (event.value.status === 'cancelled') return 'red';
  if (event.value.is_accepting_registrations) return 'green';
  return 'gold';
});

async function loadEvent() {
  const slug = route.params.slug as string;
  if (slug) {
    await Promise.all([
      eventsStore.fetchEventBySlug(slug),
      eventsStore.fetchCalendarLinks(slug),
    ]);
  }
}

function handleRsvpSuccess(_registration: Registration) {
  // Re-fetch event to update remaining capacity and status
  const slug = route.params.slug as string;
  if (slug) {
    eventsStore.fetchEventBySlug(slug);
  }
}

watch(
  () => route.params.slug,
  (newSlug) => {
    if (newSlug) {
      loadEvent();
    }
  }
);

onMounted(() => {
  loadEvent();
});
</script>
