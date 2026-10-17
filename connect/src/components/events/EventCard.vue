<template>
  <div
    class="community-card flex flex-col bg-white border border-[#ebe8de] overflow-hidden hover:border-[#640c0e]/30 hover:shadow-md transition-all duration-200 group"
  >
    <!-- Card Media Header / Motif -->
    <div class="relative h-44 w-full bg-gradient-to-br from-[#640c0e] via-[#7d1316] to-[#b02e32] overflow-hidden shrink-0 flex items-center justify-center">
      <img
        v-if="event.banner_url"
        :src="event.banner_url"
        :alt="event.name"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
        loading="lazy"
      />
      <div v-else class="text-center p-4">
        <div class="w-12 h-12 mx-auto rounded-xl bg-white/10 backdrop-blur-xs border border-white/20 flex items-center justify-center p-2 group-hover:scale-110 transition-transform duration-300 shadow-2xs">
          <img src="/logo.webp" alt="SFU MSA" class="w-full h-full object-contain filter drop-shadow" />
        </div>
        <span class="text-[11px] font-semibold text-[#ffdc83]/90 tracking-wider uppercase mt-2 block">
          SFU MSA Gathering
        </span>
      </div>

      <!-- Top Overlay Badges -->
      <div class="absolute top-3 left-3 right-3 flex items-center justify-between pointer-events-none gap-2">
        <div class="flex items-center gap-1.5 flex-wrap">
          <Badge v-if="event.is_featured" variant="gold" size="sm">
            Featured
          </Badge>
          <Badge v-if="event.category?.name" variant="neutral" size="sm" class="bg-white/95 text-neutral-800 backdrop-blur-xs font-semibold">
            {{ event.category.name }}
          </Badge>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <Badge v-if="event.status === 'cancelled'" variant="red" size="sm">
            Cancelled
          </Badge>
          <Badge v-else-if="event.is_sold_out || event.is_full" variant="red" size="sm">
            {{ event.waitlist_enabled ? 'Waitlist Open' : 'Full' }}
          </Badge>
          <Badge v-else-if="event.is_accepting_registrations" variant="green" size="sm">
            Open RSVP
          </Badge>
        </div>
      </div>
    </div>

    <!-- Card Content -->
    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
      <div class="space-y-3">
        <!-- Date Badge & Title Row -->
        <div class="flex items-start gap-3">
          <!-- Calendar Stacked Date Box -->
          <div class="w-11 h-12 rounded-xl bg-[#fffbf4] border border-[#ebe8de] flex flex-col items-center justify-center shrink-0 shadow-2xs group-hover:border-[#640c0e]/30 transition-colors">
            <span class="text-[10px] font-extrabold uppercase text-[#b02e32] leading-none">{{ monthShort }}</span>
            <span class="text-sm font-bold text-[#640c0e] leading-tight mt-0.5">{{ dayNumber }}</span>
          </div>

          <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-[#640c0e] group-hover:text-[#b02e32] transition-colors line-clamp-2 leading-snug">
              {{ event.name }}
            </h3>
            <span class="text-[11px] text-neutral-500 block mt-0.5">{{ formattedTime }}</span>
          </div>
        </div>

        <!-- Venue / Location -->
        <div class="flex items-center gap-2 text-xs text-neutral-600">
          <MapPin class="w-3.5 h-3.5 text-[#b02e32] shrink-0" />
          <span class="truncate">{{ event.location || 'SFU Burnaby Campus / Location TBA' }}</span>
        </div>

        <!-- Short Description -->
        <p v-if="event.short_description" class="text-xs text-neutral-600 line-clamp-2 leading-relaxed">
          {{ event.short_description }}
        </p>
      </div>

      <!-- Footer & Action -->
      <div class="pt-3 border-t border-[#ebe8de]/70 flex items-center justify-between gap-3">
        <!-- Capacity / Free Badge -->
        <div class="text-[11px] text-neutral-600 font-medium truncate">
          <span v-if="event.remaining_capacity !== null && event.remaining_capacity > 0 && !event.is_full" class="text-emerald-700 font-semibold">
            {{ event.remaining_capacity }} spots left
          </span>
          <span v-else-if="event.is_full && event.waitlist_enabled" class="text-amber-800 font-semibold">
            Waitlist Available
          </span>
          <span v-else class="text-neutral-600">
            {{ event.registration_label || 'Free Admission' }}
          </span>
        </div>

        <!-- Navigation Button -->
        <router-link :to="`/app/events/${event.slug}`">
          <Button variant="secondary" size="sm">
            <span>Details</span>
            <template #right-icon>
              <ArrowRight class="w-3.5 h-3.5" />
            </template>
          </Button>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PublicEvent } from '@/types/ems';
import { formatEventTime } from '@/utils/formatters';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import { MapPin, ArrowRight } from 'lucide-vue-next';

interface Props {
  event: PublicEvent;
}

const props = defineProps<Props>();

const dateObj = computed(() => {
  if (!props.event.start_at) return null;
  return new Date(props.event.start_at);
});

const monthShort = computed(() => {
  if (!dateObj.value || isNaN(dateObj.value.getTime())) return 'TBA';
  return dateObj.value.toLocaleDateString(undefined, { month: 'short', timeZone: props.event.timezone || 'UTC' }).toUpperCase();
});

const dayNumber = computed(() => {
  if (!dateObj.value || isNaN(dateObj.value.getTime())) return '--';
  return dateObj.value.toLocaleDateString(undefined, { day: 'numeric', timeZone: props.event.timezone || 'UTC' });
});

const formattedTime = computed(() => {
  return formatEventTime(props.event.start_at, props.event.end_at, props.event.timezone);
});
</script>
