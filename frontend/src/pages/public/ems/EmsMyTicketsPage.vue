<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Calendar, Clock, MapPin, Loader2, ArrowRight, Ticket, ShieldAlert } from 'lucide-vue-next';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import { EMS_PUBLIC_EVENTS_PATH } from '@/constants/ems';
import type { PublicRegistration } from '@/types/ems/public';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();
const { formatDate, formatTime } = useEventFormatting();

const registrations = ref<PublicRegistration[]>([]);
const loading = ref(true);
const error = ref('');
const activeTab = ref<'active' | 'past'>('active');

// Cancellation state
const isCancelling = ref(false);
const registrationToCancel = ref<PublicRegistration | null>(null);
const showCancelConfirm = ref(false);

useSeo(() => ({
  title: 'My Tickets | SFU MSA',
  description: 'Manage your event registrations and tickets.',
}));

async function load() {
  loading.value = true;
  error.value = '';

  try {
    registrations.value = await publicEventsService.getMyTickets();
  } catch (err) {
    error.value = err instanceof EmsApiError ? err.message : 'Failed to retrieve your tickets.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => void load());

const isPastEvent = (dateStr: string | null): boolean => {
  if (!dateStr) return false;
  return new Date(dateStr) < new Date();
};

const filteredRegistrations = computed(() => {
  return registrations.value.filter((reg) => {
    const isPast = isPastEvent(reg.event?.start_at ?? null);
    return activeTab.value === 'active' ? !isPast : isPast;
  });
});

const getStatusClass = (status: string) => {
  switch (status) {
    case 'confirmed':
      return 'bg-green-50 text-green-700 border-green-200';
    case 'waitlisted':
      return 'bg-amber-50 text-amber-700 border-amber-200';
    case 'cancelled':
      return 'bg-red-50 text-red-700 border-red-200';
    default:
      return 'bg-neutral-50 text-neutral-600 border-neutral-200';
  }
};

const confirmCancel = (reg: PublicRegistration) => {
  registrationToCancel.value = reg;
  showCancelConfirm.value = true;
};

const executeCancel = async () => {
  if (!registrationToCancel.value) return;
  isCancelling.value = true;

  try {
    await publicEventsService.cancelRegistration(registrationToCancel.value.uuid);
    toast.success('Registration cancelled successfully.');
    showCancelConfirm.value = false;
    registrationToCancel.value = null;
    await load();
  } catch (err) {
    const msg = err instanceof EmsApiError ? err.message : 'Failed to cancel registration.';
    toast.error(msg);
  } finally {
    isCancelling.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <div class="container-custom pt-28">
      
      <!-- Page Header -->
      <div class="max-w-4xl mx-auto mb-10">
        <h1 class="font-display text-4xl font-extrabold text-primary tracking-tight">My Tickets</h1>
        <p class="mt-2 text-neutral-black/55">
          View your registered events, download tickets, or manage bookings.
        </p>
      </div>

      <!-- Main Layout -->
      <div class="max-w-4xl mx-auto">
        <!-- Tabs -->
        <div class="flex border-b border-neutral-ivory mb-8">
          <button
            @click="activeTab = 'active'"
            :class="[
              'px-6 py-3 text-xs font-extrabold uppercase tracking-widest border-b-2 transition-all cursor-pointer',
              activeTab === 'active'
                ? 'border-primary text-primary'
                : 'border-transparent text-neutral-black/45 hover:text-primary'
            ]"
          >
            Active Events
          </button>
          <button
            @click="activeTab = 'past'"
            :class="[
              'px-6 py-3 text-xs font-extrabold uppercase tracking-widest border-b-2 transition-all cursor-pointer',
              activeTab === 'past'
                ? 'border-primary text-primary'
                : 'border-transparent text-neutral-black/45 hover:text-primary'
            ]"
          >
            Past Events
          </button>
        </div>

        <!-- States -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-neutral-ivory shadow-sm">
          <Loader2 class="h-8 w-8 text-primary animate-spin" />
          <p class="mt-4 text-xs font-extrabold uppercase tracking-widest text-neutral-black/45">Loading tickets...</p>
        </div>

        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-3xl p-6 text-center">
          <ShieldAlert class="h-10 w-10 text-secondary mx-auto mb-3" />
          <h3 class="font-display text-lg font-bold text-secondary">Could not load tickets</h3>
          <p class="mt-1 text-sm text-secondary/80">{{ error }}</p>
          <button @click="load" class="mt-4 bg-secondary text-white px-5 py-2 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary/90 transition-all">
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="filteredRegistrations.length === 0"
          class="bg-white rounded-3xl border border-neutral-ivory shadow-sm p-12 text-center"
        >
          <div class="h-16 w-16 bg-neutral-background rounded-2xl flex items-center justify-center mx-auto mb-4 border border-neutral-ivory">
            <Ticket class="h-8 w-8 text-primary/40" />
          </div>
          <h3 class="font-display text-xl font-extrabold text-primary">No tickets found</h3>
          <p class="mt-2 text-sm text-neutral-black/55 max-w-sm mx-auto">
            You don't have any {{ activeTab }} event tickets. Browse our upcoming community events and register.
          </p>
          <RouterLink
            :to="EMS_PUBLIC_EVENTS_PATH"
            class="mt-6 inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary hover:shadow-premium transition-all"
          >
            Browse Events
            <ArrowRight class="h-3.5 w-3.5" />
          </RouterLink>
        </div>

        <!-- Registrations List -->
        <div v-else class="space-y-6">
          <div
            v-for="reg in filteredRegistrations"
            :key="reg.uuid"
            class="bg-white rounded-3xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all overflow-hidden flex flex-col md:flex-row md:items-stretch"
          >
            <!-- Card Left: Event Info -->
            <div class="p-6 md:p-8 flex-1 flex flex-col justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border',
                      getStatusClass(reg.status)
                    ]"
                  >
                    {{ reg.status_label }}
                  </span>
                  <span class="text-[10px] font-mono text-neutral-black/35 font-bold">
                    Ref: {{ reg.reference }}
                  </span>
                </div>
                
                <h3 class="font-display text-xl sm:text-2xl font-black text-primary leading-snug">
                  {{ reg.event?.name }}
                </h3>

                <div class="mt-4 space-y-2.5 text-xs text-neutral-black/60">
                  <div class="flex items-center gap-2">
                    <Calendar class="text-primary shrink-0" :size="14" />
                    <span>{{ formatDate(reg.event?.start_at) }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <Clock class="text-primary shrink-0" :size="14" />
                    <span>{{ formatTime(reg.event?.start_at) }}</span>
                  </div>
                  <div v-if="reg.event?.location" class="flex items-center gap-2">
                    <MapPin class="text-primary shrink-0" :size="14" />
                    <span>{{ reg.event.location }}</span>
                  </div>
                </div>
              </div>

              <!-- Ticket Info & Links -->
              <div v-if="reg.status !== 'cancelled' && reg.tickets && reg.tickets.length > 0" class="mt-6 pt-5 border-t border-dashed border-neutral-ivory">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/40 block mb-2">
                  Your Tickets ({{ reg.tickets.length }}):
                </span>
                <div class="flex flex-wrap gap-2">
                  <RouterLink
                    v-for="tkt in reg.tickets"
                    :key="tkt.code"
                    :to="{ name: 'ems-public-ticket', params: { code: tkt.code } }"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-background hover:bg-primary/5 border border-neutral-ivory hover:border-primary/20 text-neutral-black hover:text-primary transition-all text-xs font-semibold"
                  >
                    <Ticket :size="12" />
                    <span>{{ tkt.code }}</span>
                  </RouterLink>
                </div>
              </div>
            </div>

            <!-- Card Right: Action Panel -->
            <div class="px-6 pb-6 md:pb-0 md:px-8 border-t md:border-t-0 md:border-l border-neutral-ivory bg-neutral-background/30 flex flex-col justify-center min-w-[200px] shrink-0">
              <div class="space-y-2 md:space-y-3">
                <template v-if="reg.status !== 'cancelled'">
                  <!-- Browse Ticket Option -->
                  <RouterLink
                    v-if="reg.tickets && reg.tickets.length > 0"
                    :to="{ name: 'ems-public-ticket', params: { code: reg.tickets[0].code } }"
                    class="block w-full text-center bg-primary text-white py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary transition-all hover:shadow-soft"
                  >
                    View Ticket
                  </RouterLink>

                  <!-- Cancel Button -->
                  <button
                    @click="confirmCancel(reg)"
                    class="block w-full text-center border border-red-200 text-red-600 hover:bg-red-50 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
                  >
                    Cancel Booking
                  </button>
                </template>
                <div v-else class="text-center py-6 md:py-0">
                  <span class="text-xs text-neutral-black/45 font-medium italic">Booking Cancelled</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Cancellation Confirmation Modal -->
    <div
      v-if="showCancelConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary/20 backdrop-blur-sm"
    >
      <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 max-w-md w-full shadow-2xl">
        <div class="flex items-center gap-3 text-secondary mb-4">
          <ShieldAlert class="h-6 w-6 shrink-0" />
          <h3 class="font-display text-xl font-bold">Cancel Registration?</h3>
        </div>
        <p class="text-sm text-neutral-black/60 leading-relaxed">
          Are you sure you want to cancel your registration for <strong class="text-neutral-black">{{ registrationToCancel?.event?.name }}</strong>?
          This will void all associated ticket QR codes and release your spot. This action cannot be undone.
        </p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="showCancelConfirm = false"
            :disabled="isCancelling"
            class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background text-neutral-black rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
          >
            No, Keep It
          </button>
          <button
            @click="executeCancel"
            :disabled="isCancelling"
            class="px-4 py-2 bg-secondary text-white hover:bg-secondary/90 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all inline-flex items-center gap-2 cursor-pointer"
          >
            <Loader2 v-if="isCancelling" class="h-3.5 w-3.5 animate-spin" />
            Yes, Cancel Booking
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
