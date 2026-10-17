<template>
  <div
    class="ticket-pass bg-white border border-[#ebe8de] transition-all duration-200 relative"
    :class="registration.is_active ? 'hover:border-[#640c0e]/40 shadow-xs hover:shadow-brand' : 'opacity-85 bg-neutral-50/50'"
  >
    <!-- Ticket pass notch cutout accents -->
    <div class="hidden md:block ticket-notch-left"></div>
    <div class="hidden md:block ticket-notch-right"></div>

    <!-- Digital Pass Header -->
    <div class="p-5 sm:p-6 border-b border-[#ebe8de]/70 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-white via-white to-[#fffbf4]">
      <div class="space-y-1">
        <div class="flex items-center gap-2 flex-wrap">
          <Badge
            :variant="statusBadgeVariant"
            size="sm"
          >
            {{ registration.status_label || registration.status }}
          </Badge>
          <span class="text-xs font-mono font-semibold text-neutral-500">
            Pass #{{ registration.reference }}
          </span>
          <Badge v-if="registration.ticket_type?.name" variant="neutral" size="sm">
            {{ registration.ticket_type.name }}
          </Badge>
        </div>

        <h3 class="text-lg font-bold text-[#640c0e] font-display">
          <router-link
            v-if="registration.event?.slug"
            :to="`/app/events/${registration.event.slug}`"
            class="hover:text-[#b02e32] transition-colors"
          >
            {{ registration.event?.name || 'SFU MSA Gathering' }}
          </router-link>
          <span v-else>{{ registration.event?.name || 'SFU MSA Gathering' }}</span>
        </h3>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <!-- Download ICS -->
        <a
          v-if="registration.event?.slug"
          :href="icsDownloadUrl"
          download
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4] hover:bg-[#ebe8de]/70 text-neutral-700 text-xs font-semibold transition-colors shadow-2xs"
          title="Download calendar file (.ics)"
        >
          <CalendarPlus class="w-3.5 h-3.5 text-[#b02e32]" />
          <span>Add to Calendar</span>
        </a>

        <!-- Cancel Reservation -->
        <Button
          v-if="canCancel"
          variant="outline"
          size="sm"
          class="text-red-700 hover:bg-red-50 hover:border-red-200 border-[#ebe8de]"
          @click="$emit('cancel', registration)"
        >
          <span>Cancel Pass</span>
        </Button>
      </div>
    </div>

    <!-- Pass Body with Perforated Divider -->
    <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
      <!-- Left Column: Details -->
      <div class="space-y-3 md:col-span-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div class="space-y-1">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">Date & Time</span>
            <div class="flex items-start gap-2.5 text-neutral-800">
              <Calendar class="w-4 h-4 text-[#b02e32] shrink-0 mt-0.5" />
              <div>
                <p class="font-bold text-neutral-800">{{ formattedDate }}</p>
                <p class="text-neutral-500">{{ formattedTime }}</p>
              </div>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">Campus Venue</span>
            <div class="flex items-start gap-2.5 text-neutral-800">
              <MapPin class="w-4 h-4 text-[#b02e32] shrink-0 mt-0.5" />
              <p class="font-medium text-neutral-800">{{ registration.event?.location || 'Burnaby Campus / Location TBA' }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">Pass Holder</span>
            <div class="flex items-start gap-2.5 text-neutral-800">
              <User class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
              <div>
                <p class="font-medium text-neutral-800">{{ registration.attendee_name }}</p>
                <p class="text-neutral-500 truncate max-w-[200px]">{{ registration.attendee_email }}</p>
              </div>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">Admission Type</span>
            <div class="flex items-start gap-2.5 text-neutral-800">
              <TicketIcon class="w-4 h-4 text-[#b02e32] shrink-0 mt-0.5" />
              <p class="font-semibold text-neutral-800">
                {{ registration.quantity }} × {{ registration.amount_due === 0 ? 'Free Admission' : `$${registration.amount_due}` }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Check-in Stub with QR code (perforated appearance) -->
      <div class="flex flex-col items-center justify-center p-4 rounded-2xl bg-gradient-to-b from-[#fffbf4] to-white border border-[#ebe8de] text-center space-y-2 border-dashed shadow-2xs">
        <template v-if="primaryTicket">
          <!-- Valid PNG QR Image from API with protocol validation -->
          <div v-if="hasValidQrImage" class="w-28 h-28 bg-white p-2 rounded-xl border border-[#ebe8de] shadow-xs flex items-center justify-center">
            <img
              :src="safeQrImage"
              :alt="`Check-in QR for ticket ${primaryTicket.code}`"
              class="w-full h-full object-contain"
            />
          </div>
          <!-- Fallback when QR image is omitted or invalid -->
          <div v-else class="w-24 h-24 rounded-xl bg-white border border-[#ebe8de] flex flex-col items-center justify-center text-neutral-400 p-2">
            <QrCode class="w-8 h-8 text-[#640c0e]/60" />
            <span class="text-[9px] mt-1 text-neutral-500 font-medium">Entrance Scan</span>
          </div>

          <div class="space-y-0.5">
            <span class="text-[10px] uppercase font-bold text-neutral-400 block tracking-wider">Ticket Code</span>
            <span class="text-xs font-mono font-bold text-[#640c0e] tracking-wider block">
              {{ primaryTicket.code }}
            </span>
          </div>
        </template>

        <template v-else>
          <div class="space-y-1 py-3 text-neutral-500">
            <TicketIcon class="w-8 h-8 text-neutral-300 mx-auto" />
            <span class="text-xs font-mono font-bold text-[#640c0e]">{{ registration.reference }}</span>
            <p class="text-[11px] text-neutral-400">Present booking reference at venue entrance</p>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Registration, Ticket } from '@/types/ems';
import { formatEventDate, formatEventTime } from '@/utils/formatters';
import { isValidQrImageSource } from '@/utils/security';
import emsService from '@/services/ems.service';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import {
  Calendar,
  MapPin,
  User,
  Ticket as TicketIcon,
  CalendarPlus,
  QrCode,
} from 'lucide-vue-next';

interface Props {
  registration: Registration;
}

const props = defineProps<Props>();
defineEmits<{
  (e: 'cancel', registration: Registration): void;
}>();

const primaryTicket = computed<Ticket | null>(() => {
  if (props.registration.tickets && props.registration.tickets.length > 0) {
    return props.registration.tickets[0];
  }
  return null;
});

const hasValidQrImage = computed<boolean>(() => {
  return isValidQrImageSource(primaryTicket.value?.qr_image);
});

const safeQrImage = computed<string>(() => {
  return hasValidQrImage.value && primaryTicket.value?.qr_image ? primaryTicket.value.qr_image : '';
});

const canCancel = computed<boolean>(() => {
  return props.registration.is_active && props.registration.status !== 'cancelled';
});

const formattedDate = computed(() => {
  return formatEventDate(props.registration.event?.start_at);
});

const formattedTime = computed(() => {
  return formatEventTime(props.registration.event?.start_at, props.registration.event?.end_at);
});

const icsDownloadUrl = computed(() => {
  if (!props.registration.event?.slug) return '#';
  return emsService.getIcsDownloadUrl(props.registration.event.slug);
});

const statusBadgeVariant = computed<'green' | 'red' | 'gold' | 'neutral'>(() => {
  const status = props.registration.status?.toLowerCase();
  if (status === 'confirmed' || status === 'completed') return 'green';
  if (status === 'cancelled') return 'red';
  if (status === 'waitlisted' || status === 'pending') return 'gold';
  return 'neutral';
});
</script>
