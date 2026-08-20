<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Calendar, Clock, Download, MapPin, Printer } from 'lucide-vue-next';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import { EMS_PUBLIC_EVENTS_PATH } from '@/constants/ems';
import type { PublicTicket } from '@/types/ems/public';

const route = useRoute();
const { formatDateRange, formatTimeRange } = useEventFormatting();

const code = computed(() => String(route.params.code || '').toUpperCase());
const ticket = ref<PublicTicket | null>(null);
const loading = ref(true);
const error = ref('');

useSeo(() => ({
  title: ticket.value?.event?.name
    ? `Ticket · ${ticket.value.event.name} | SFU MSA`
    : 'My Ticket | SFU MSA',
  description: 'Your SFU MSA event ticket and QR code.',
}));

async function load() {
  loading.value = true;
  error.value = '';

  try {
    ticket.value = await publicEventsService.getTicket(code.value);
  } catch (err) {
    ticket.value = null;
    error.value = err instanceof EmsApiError ? err.message : 'Ticket not found.';
  } finally {
    loading.value = false;
  }
}

watch(code, () => void load());
onMounted(() => void load());

function printTicket() {
  window.print();
}

function downloadQr() {
  if (!ticket.value?.qr_image) return;

  const link = document.createElement('a');
  link.href = ticket.value.qr_image;
  link.download = `${ticket.value.code}.png`;
  link.click();
}
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24 print:bg-white print:pb-0">
    <div class="container-custom pt-28 print:pt-6">
      <div v-if="loading" class="max-w-lg mx-auto h-96 rounded-[2rem] bg-white border border-neutral-ivory animate-pulse" />

      <div v-else-if="error" class="max-w-lg mx-auto text-center py-16">
        <h1 class="font-display text-3xl font-bold">Ticket not found</h1>
        <p class="mt-2 text-neutral-black/55">{{ error }}</p>
        <RouterLink :to="EMS_PUBLIC_EVENTS_PATH" class="mt-6 inline-block text-sm font-bold text-primary">
          Browse events
        </RouterLink>
      </div>

      <article
        v-else-if="ticket"
        class="ticket-sheet max-w-lg mx-auto rounded-[2rem] border border-neutral-ivory bg-white shadow-sm overflow-hidden print:shadow-none print:border-neutral-300"
      >
        <header class="bg-primary text-white px-6 py-5 print:bg-white print:text-neutral-black print:border-b">
          <p class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-accent-gold print:text-neutral-black/50">
            My Ticket
          </p>
          <h1 class="font-display text-2xl sm:text-3xl font-black mt-1 leading-tight">
            {{ ticket.event?.name || 'SFU MSA Event' }}
          </h1>
        </header>

        <div class="p-6 sm:p-8 space-y-6">
          <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3">
              <Calendar class="text-primary shrink-0" :size="16" />
              <span>{{ formatDateRange(ticket.event?.start_at, ticket.event?.end_at) }}</span>
            </div>
            <div class="flex items-center gap-3">
              <Clock class="text-primary shrink-0" :size="16" />
              <span>{{ formatTimeRange(ticket.event?.start_at, ticket.event?.end_at) }}</span>
            </div>
            <div v-if="ticket.event?.location" class="flex items-center gap-3">
              <MapPin class="text-primary shrink-0" :size="16" />
              <span>{{ ticket.event.location }}</span>
            </div>
          </div>

          <div class="flex flex-col items-center gap-3 py-4 border-y border-dashed border-neutral-ivory">
            <img
              v-if="ticket.qr_image"
              :src="ticket.qr_image"
              alt="Ticket QR code"
              class="w-48 h-48 rounded-xl bg-white"
            />
            <p class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">
              Present this QR code at check-in
            </p>
          </div>

          <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Ticket ID</dt>
              <dd class="mt-1 font-mono font-semibold tracking-wide break-all">{{ ticket.code }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Ticket status</dt>
              <dd class="mt-1 font-semibold">{{ ticket.status_label }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Registration</dt>
              <dd class="mt-1 font-semibold">{{ ticket.registration?.status_label || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Attendee</dt>
              <dd class="mt-1 font-semibold">{{ ticket.holder_name || '—' }}</dd>
            </div>
            <div v-if="ticket.registration?.reference" class="col-span-2">
              <dt class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Reference</dt>
              <dd class="mt-1 font-mono text-xs">{{ ticket.registration.reference }}</dd>
            </div>
          </dl>

          <div class="flex flex-wrap gap-3 print:hidden">
            <button
              type="button"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-xs font-extrabold uppercase tracking-widest cursor-pointer"
              @click="printTicket"
            >
              <Printer :size="14" /> Print
            </button>
            <button
              v-if="ticket.qr_image"
              type="button"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-ivory text-xs font-extrabold uppercase tracking-widest cursor-pointer"
              @click="downloadQr"
            >
              <Download :size="14" /> Download QR
            </button>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
@media print {
  :global(body *) {
    visibility: hidden;
  }

  .ticket-sheet,
  .ticket-sheet * {
    visibility: visible;
  }

  .ticket-sheet {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    max-width: 100%;
  }
}
</style>
