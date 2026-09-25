<script setup lang="ts">
import { computed } from 'vue';
import { Calendar, MapPin, Layers, ChevronRight, Sparkles } from 'lucide-vue-next';
import VolunteerCapacityBadge from './VolunteerCapacityBadge.vue';
import type { VolunteerOpportunity } from '@/services/volunteeringService';

const props = defineProps<{
  opportunity: VolunteerOpportunity;
}>();

const formattedDate = computed(() => {
  if (!props.opportunity.start_at) return null;
  return new Date(props.opportunity.start_at).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
});

const totalShifts = computed(() => {
  return props.opportunity.shifts?.length || 0;
});

const totalTeams = computed(() => {
  return props.opportunity.teams?.length || 0;
});
</script>

<template>
  <div
    class="group bg-white rounded-3xl border border-neutral-ivory/80 shadow-soft hover:shadow-premium p-6 sm:p-7 flex flex-col justify-between transition-all duration-300 hover:-translate-y-1 relative overflow-hidden"
  >
    <!-- Top Accent Stripe -->
    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-primary via-secondary to-accent-gold opacity-80 group-hover:opacity-100 transition-opacity" />

    <div class="space-y-4">
      <!-- Badge Bar -->
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <!-- Event-Linked Badge vs Standalone Badge -->
        <router-link
          v-if="opportunity.event"
          :to="`/events/${opportunity.event.slug}`"
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20 transition-all"
        >
          <Sparkles class="w-3.5 h-3.5" />
          Event: {{ opportunity.event.name }}
        </router-link>
        <span
          v-else
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-neutral-ivory/60 text-neutral-black/70 border border-neutral-ivory"
        >
          Community Volunteer
        </span>

        <!-- Capacity Badge -->
        <VolunteerCapacityBadge
          :status="opportunity.status"
          :capacity="opportunity.capacity"
          :signups-count="opportunity.signups_count"
          compact
        />
      </div>

      <!-- Title & Description -->
      <div>
        <h3 class="text-xl sm:text-2xl font-display font-extrabold text-primary group-hover:text-secondary transition-colors leading-tight">
          <router-link :to="`/volunteer/${opportunity.slug}`">
            {{ opportunity.title }}
          </router-link>
        </h3>
        <p class="text-neutral-black/70 text-sm mt-2.5 line-clamp-3 leading-relaxed">
          {{ opportunity.description || 'Join our volunteer team to help support the SFU Muslim community.' }}
        </p>
      </div>

      <!-- Meta Info Pills -->
      <div class="pt-3 border-t border-neutral-ivory/80 space-y-2 text-xs font-semibold text-neutral-black/70">
        <div v-if="opportunity.location" class="flex items-center gap-2">
          <MapPin class="w-4 h-4 text-primary shrink-0" />
          <span class="truncate">{{ opportunity.location }}</span>
        </div>

        <div v-if="formattedDate" class="flex items-center gap-2">
          <Calendar class="w-4 h-4 text-primary shrink-0" />
          <span>{{ formattedDate }}</span>
        </div>

        <div class="flex items-center gap-2 text-neutral-muted text-[11px] uppercase tracking-wider font-mono pt-1">
          <Layers class="w-3.5 h-3.5 text-secondary shrink-0" />
          <span>{{ totalTeams }} Teams &bull; {{ totalShifts }} Shifts</span>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <div class="pt-6">
      <router-link
        :to="`/volunteer/${opportunity.slug}`"
        class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition-all shadow-brand hover:shadow-premium group-hover:gap-3"
      >
        <span>View & Sign Up</span>
        <ChevronRight class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
      </router-link>
    </div>
  </div>
</template>
