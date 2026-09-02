<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Calendar, Clock, MapPin, Loader2, ArrowRight, Ticket, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import pendingCheckoutStorage, { type StoredPendingCheckout } from '@/services/ems/pendingCheckoutStorage';
import hiddenTicketsStorage from '@/services/ems/hiddenTicketsStorage';
import { EMS_PUBLIC_EVENTS_PATH, emsPublicEventPath } from '@/constants/ems';
import type { PublicRegistration } from '@/types/ems/public';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();
const { formatDateRange, formatTimeRange } = useEventFormatting();

const registrations = ref<PublicRegistration[]>([]);
const localPending = ref<StoredPendingCheckout[]>([]);
const hiddenRevision = ref(0);
const loading = ref(true);
const error = ref('');
const activeTab = ref<'active' | 'past'>('active');
const payingKey = ref('');

const isCancelling = ref(false);
const registrationToCancel = ref<PublicRegistration | null>(null);
const showCancelConfirm = ref(false);

const isCancellingPending = ref(false);
const pendingPaymentToCancel = ref<{
  kind: 'registration' | 'local';
  registration?: PublicRegistration;
  local?: StoredPendingCheckout;
} | null>(null);
const showCancelPendingConfirm = ref(false);

const registrationToHide = ref<PublicRegistration | null>(null);
const pendingToHide = ref<StoredPendingCheckout | null>(null);
const showHideConfirm = ref(false);

useSeo(() => ({
  title: 'My Tickets | SFU MSA',
  description: 'Manage your event registrations and tickets.',
}));

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount);
}

async function load() {
  loading.value = true;
  error.value = '';
  localPending.value = pendingCheckoutStorage.list();

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

const extraPending = computed(() => {
  if (activeTab.value !== 'active') return [];
  hiddenRevision.value;
  const slugs = new Set(registrations.value.map((reg) => reg.event?.slug).filter(Boolean));
  return localPending.value.filter(
    (item) => !slugs.has(item.slug) && !hiddenTicketsStorage.hasPending(item.slug)
  );
});

const isRegistrationActive = (reg: PublicRegistration): boolean => {
  if (reg.is_active !== undefined) return reg.is_active;
  return ['confirmed', 'awaiting_payment', 'waitlisted'].includes(reg.status);
};

const filteredRegistrations = computed(() => {
  hiddenRevision.value;
  return registrations.value.filter((reg) => {
    if (hiddenTicketsStorage.hasRegistration(reg.uuid)) return false;
    const isPast = isPastEvent(reg.event?.end_at ?? reg.event?.start_at ?? null);
    const active = isRegistrationActive(reg);
    return activeTab.value === 'active' ? (active && !isPast) : (!active || isPast);
  });
});

const showEmpty = computed(() =>
  filteredRegistrations.value.length === 0 && extraPending.value.length === 0
);

const getStatusClass = (status: string) => {
  switch (status) {
    case 'confirmed':
      return 'bg-green-50 text-green-700 border-green-200';
    case 'waitlisted':
    case 'awaiting_payment':
      return 'bg-amber-50 text-amber-700 border-amber-200';
    case 'refunded':
      return 'bg-purple-50 text-purple-700 border-purple-200';
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

function canCancelPendingRegistration(reg: PublicRegistration): boolean {
  return (
    reg.status === 'awaiting_payment' &&
    Boolean(reg.event?.slug) &&
    Boolean(reg.attendee_email) &&
    Boolean(reg.pending_checkout?.order_uuid)
  );
}

function canCancelLocalPending(item: StoredPendingCheckout): boolean {
  return Boolean(item.slug && item.email && item.order_uuid);
}

function confirmCancelPendingRegistration(reg: PublicRegistration) {
  pendingPaymentToCancel.value = { kind: 'registration', registration: reg };
  showCancelPendingConfirm.value = true;
}

function confirmCancelLocalPending(item: StoredPendingCheckout) {
  pendingPaymentToCancel.value = { kind: 'local', local: item };
  showCancelPendingConfirm.value = true;
}

function pendingCancelLabel(): string {
  const target = pendingPaymentToCancel.value;
  if (target?.kind === 'registration') {
    return target.registration?.event?.name ?? 'this event';
  }
  return target?.local?.event_name ?? 'this event';
}

async function executeCancelPendingPayment() {
  const target = pendingPaymentToCancel.value;
  if (!target || isCancellingPending.value) return;

  const slug =
    target.kind === 'registration'
      ? target.registration?.event?.slug
      : target.local?.slug;
  const email =
    target.kind === 'registration'
      ? target.registration?.attendee_email
      : target.local?.email;
  const orderUuid =
    target.kind === 'registration'
      ? target.registration?.pending_checkout?.order_uuid
      : target.local?.order_uuid;

  if (!slug || !email || !orderUuid) {
    toast.error('Missing checkout details. Return to the event page to cancel.');
    return;
  }

  isCancellingPending.value = true;
  try {
    await publicEventsService.cancelCheckout(slug, email, orderUuid);
    pendingCheckoutStorage.remove(slug);
    toast.success('Pending payment cancelled.');
    showCancelPendingConfirm.value = false;
    pendingPaymentToCancel.value = null;
    await load();
  } catch (err) {
    toast.error(err instanceof EmsApiError ? err.message : 'Failed to cancel pending payment.');
  } finally {
    isCancellingPending.value = false;
  }
}

const HIDEABLE_EVENT_STATUSES = new Set(['cancelled', 'completed', 'archived']);

const canHideRegistration = (reg: PublicRegistration): boolean => {
  if (reg.status === 'cancelled' || reg.status === 'refunded') return true;
  if (reg.event?.status && HIDEABLE_EVENT_STATUSES.has(reg.event.status)) return true;
  return isPastEvent(reg.event?.end_at ?? reg.event?.start_at ?? null);
};

const confirmHideRegistration = (reg: PublicRegistration) => {
  registrationToHide.value = reg;
  pendingToHide.value = null;
  showHideConfirm.value = true;
};

const confirmHidePending = (item: StoredPendingCheckout) => {
  pendingToHide.value = item;
  registrationToHide.value = null;
  showHideConfirm.value = true;
};

const executeHide = () => {
  if (registrationToHide.value) {
    hiddenTicketsStorage.hideRegistration(registrationToHide.value.uuid);
  }
  if (pendingToHide.value) {
    hiddenTicketsStorage.hidePending(pendingToHide.value.slug);
    pendingCheckoutStorage.remove(pendingToHide.value.slug);
  }
  hiddenRevision.value += 1;
  showHideConfirm.value = false;
  registrationToHide.value = null;
  pendingToHide.value = null;
  toast.success('Removed from My Tickets.');
};

const executeCancel = async () => {
  if (!registrationToCancel.value) return;
  isCancelling.value = true;

  try {
    await publicEventsService.cancelRegistration(registrationToCancel.value.uuid);
    if (registrationToCancel.value.event?.slug) {
      pendingCheckoutStorage.remove(registrationToCancel.value.event.slug);
    }
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

async function payRegistration(reg: PublicRegistration) {
  const url = reg.pending_checkout?.checkout_url;
  if (url) {
    window.location.href = url;
    return;
  }

  if (!reg.event?.slug) return;
  payingKey.value = reg.uuid;
  try {
    const result = await publicEventsService.resumeCheckout(reg.event.slug, {
      email: reg.attendee_email,
      order_uuid: reg.pending_checkout?.order_uuid ?? undefined,
    });
    if (result.checkout_url) {
      window.location.href = result.checkout_url;
      return;
    }
  } catch (err) {
    toast.error(err instanceof EmsApiError ? err.message : 'Could not resume checkout.');
  } finally {
    payingKey.value = '';
  }
}

function payLocalPending(item: StoredPendingCheckout) {
  if (item.checkout_url) {
    window.location.href = item.checkout_url;
  }
}
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
          v-else-if="showEmpty"
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
            v-for="item in extraPending"
            :key="'pending-' + item.order_uuid"
            class="bg-white rounded-3xl border border-amber-200 shadow-soft overflow-hidden flex flex-col md:flex-row md:items-stretch"
          >
            <div class="p-6 md:p-8 flex-1">
              <span class="px-3 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-amber-50 text-amber-700 border-amber-200">
                Pending Payment
              </span>
              <h3 class="mt-3 font-display text-xl sm:text-2xl font-black text-primary leading-snug">
                {{ item.event_name }}
              </h3>
              <p class="mt-2 text-sm text-neutral-black/60">
                {{ item.ticket_name || 'Ticket' }} × {{ item.quantity }}
                · {{ formatMoney(item.amount, item.currency) }}
              </p>
              <p class="mt-1 text-xs text-neutral-black/45">
                Saved on this device for {{ item.email }}. Return to the event page if you want to change details first.
              </p>
            </div>
            <div class="px-6 pb-6 md:pb-0 md:px-8 border-t md:border-t-0 md:border-l border-neutral-ivory bg-amber-50/40 flex flex-col justify-center min-w-[200px] shrink-0">
              <div class="space-y-2 md:space-y-3">
                <button
                  v-if="item.checkout_url"
                  type="button"
                  class="block w-full text-center bg-primary text-white py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary transition-all cursor-pointer"
                  @click="payLocalPending(item)"
                >
                  Complete Payment
                </button>
                <RouterLink
                  :to="emsPublicEventPath(item.slug)"
                  class="block w-full text-center border border-neutral-ivory text-neutral-black py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:border-primary/40 transition-all"
                >
                  Review Details
                </RouterLink>
                <button
                  v-if="canCancelLocalPending(item)"
                  type="button"
                  class="block w-full text-center border border-red-200 text-red-600 hover:bg-red-50 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
                  :disabled="isCancellingPending"
                  @click="confirmCancelLocalPending(item)"
                >
                  Cancel Pending Payment
                </button>
                <button
                  type="button"
                  class="block w-full text-center border border-neutral-ivory text-neutral-black/55 hover:text-secondary hover:border-secondary/30 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
                  @click="confirmHidePending(item)"
                >
                  Remove from list
                </button>
              </div>
            </div>
          </div>

          <div
            v-for="reg in filteredRegistrations"
            :key="reg.uuid"
            class="bg-white rounded-3xl border shadow-soft hover:shadow-premium transition-all overflow-hidden flex flex-col md:flex-row md:items-stretch"
            :class="reg.status === 'awaiting_payment' ? 'border-amber-200' : 'border-neutral-ivory'"
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
                    <span>{{ formatDateRange(reg.event?.start_at, reg.event?.end_at) }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <Clock class="text-primary shrink-0" :size="14" />
                    <span>{{ formatTimeRange(reg.event?.start_at, reg.event?.end_at) }}</span>
                  </div>
                  <div v-if="reg.event?.location" class="flex items-center gap-2">
                    <MapPin class="text-primary shrink-0" :size="14" />
                    <span>{{ reg.event.location }}</span>
                  </div>
                  <p v-if="reg.status === 'awaiting_payment'" class="pt-1 font-semibold text-amber-800">
                    {{ formatMoney(reg.pending_checkout?.amount ?? reg.amount_due ?? 0, reg.pending_checkout?.currency || reg.currency || 'CAD') }}
                    still due
                    <span v-if="reg.ticket_type?.name"> · {{ reg.ticket_type.name }} × {{ reg.quantity }}</span>
                  </p>
                </div>
              </div>

              <!-- Ticket Info & Links -->
              <div v-if="isRegistrationActive(reg) && reg.tickets && reg.tickets.length > 0" class="mt-6 pt-5 border-t border-dashed border-neutral-ivory">
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
                <template v-if="reg.status === 'awaiting_payment'">
                  <button
                    type="button"
                    class="block w-full text-center bg-primary text-white py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary transition-all cursor-pointer disabled:opacity-60"
                    :disabled="payingKey === reg.uuid"
                    @click="payRegistration(reg)"
                  >
                    {{ payingKey === reg.uuid ? 'Opening…' : 'Complete Payment' }}
                  </button>
                  <RouterLink
                    v-if="reg.event?.slug"
                    :to="{ path: emsPublicEventPath(reg.event.slug), query: { resume: '1', email: reg.attendee_email } }"
                    class="block w-full text-center border border-neutral-ivory text-neutral-black py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:border-primary/40 transition-all"
                  >
                    Review Details
                  </RouterLink>
                  <button
                    v-if="canCancelPendingRegistration(reg)"
                    type="button"
                    class="block w-full text-center border border-red-200 text-red-600 hover:bg-red-50 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer disabled:opacity-60"
                    :disabled="isCancellingPending"
                    @click="confirmCancelPendingRegistration(reg)"
                  >
                    Cancel Pending Payment
                  </button>
                  <button
                    v-if="canHideRegistration(reg)"
                    type="button"
                    class="block w-full text-center border border-neutral-ivory text-neutral-black/55 hover:text-secondary hover:border-secondary/30 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer inline-flex items-center justify-center gap-1.5"
                    @click="confirmHideRegistration(reg)"
                  >
                    <Trash2 class="h-3 w-3" />
                    Remove from list
                  </button>
                </template>
                <template v-else-if="isRegistrationActive(reg)">
                  <RouterLink
                    v-if="reg.tickets && reg.tickets.length > 0"
                    :to="{ name: 'ems-public-ticket', params: { code: reg.tickets[0].code } }"
                    class="block w-full text-center bg-primary text-white py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary transition-all hover:shadow-soft"
                  >
                    View Ticket
                  </RouterLink>

                  <button
                    v-if="!isPastEvent(reg.event?.end_at ?? reg.event?.start_at ?? null)"
                    @click="confirmCancel(reg)"
                    class="block w-full text-center border border-red-200 text-red-600 hover:bg-red-50 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
                  >
                    Cancel Booking
                  </button>
                  <button
                    v-if="canHideRegistration(reg)"
                    type="button"
                    class="block w-full text-center border border-neutral-ivory text-neutral-black/55 hover:text-secondary hover:border-secondary/30 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer inline-flex items-center justify-center gap-1.5"
                    @click="confirmHideRegistration(reg)"
                  >
                    <Trash2 class="h-3 w-3" />
                    Remove from list
                  </button>
                </template>
                <div v-else-if="reg.status === 'refunded'" class="space-y-2 md:space-y-3 text-center py-2 md:py-0">
                  <span class="block text-xs text-purple-700 font-medium italic">Booking Refunded</span>
                  <button
                    type="button"
                    class="block w-full text-center border border-neutral-ivory text-neutral-black/55 hover:text-secondary hover:border-secondary/30 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer inline-flex items-center justify-center gap-1.5"
                    @click="confirmHideRegistration(reg)"
                  >
                    <Trash2 class="h-3 w-3" />
                    Remove from list
                  </button>
                </div>
                <div v-else class="space-y-2 md:space-y-3 text-center py-2 md:py-0">
                  <span class="block text-xs text-neutral-black/45 font-medium italic">Booking Cancelled</span>
                  <button
                    type="button"
                    class="block w-full text-center border border-neutral-ivory text-neutral-black/55 hover:text-secondary hover:border-secondary/30 py-2.5 px-4 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer inline-flex items-center justify-center gap-1.5"
                    @click="confirmHideRegistration(reg)"
                  >
                    <Trash2 class="h-3 w-3" />
                    Remove from list
                  </button>
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

    <!-- Cancel pending payment confirmation -->
    <div
      v-if="showCancelPendingConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary/20 backdrop-blur-sm"
    >
      <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 max-w-md w-full shadow-2xl">
        <div class="flex items-center gap-3 text-secondary mb-4">
          <ShieldAlert class="h-6 w-6 shrink-0" />
          <h3 class="font-display text-xl font-bold">Cancel Pending Payment?</h3>
        </div>
        <p class="text-sm text-neutral-black/60 leading-relaxed">
          This cancels your unpaid checkout for
          <strong class="text-neutral-black">{{ pendingCancelLabel() }}</strong>.
          The pending registration will be cancelled, your reserved spot will be released, and you will not be able to continue with this checkout.
          No payment was taken, so nothing will be refunded.
        </p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            :disabled="isCancellingPending"
            class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background text-neutral-black rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
            @click="showCancelPendingConfirm = false; pendingPaymentToCancel = null"
          >
            Keep Checkout
          </button>
          <button
            type="button"
            :disabled="isCancellingPending"
            class="px-4 py-2 bg-secondary text-white hover:bg-secondary/90 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all inline-flex items-center gap-2 cursor-pointer disabled:opacity-60"
            @click="executeCancelPendingPayment"
          >
            <Loader2 v-if="isCancellingPending" class="h-3.5 w-3.5 animate-spin" />
            Yes, Cancel Payment
          </button>
        </div>
      </div>
    </div>

    <!-- Hide from list confirmation -->
    <div
      v-if="showHideConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary/20 backdrop-blur-sm"
    >
      <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 max-w-md w-full shadow-2xl">
        <div class="flex items-center gap-3 text-primary mb-4">
          <Trash2 class="h-6 w-6 shrink-0" />
          <h3 class="font-display text-xl font-bold">Remove from My Tickets?</h3>
        </div>
        <p class="text-sm text-neutral-black/60 leading-relaxed">
          This hides
          <strong class="text-neutral-black">{{ registrationToHide?.event?.name || pendingToHide?.event_name }}</strong>
          from this list on this device. It does not cancel a booking or delete the ticket itself.
        </p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="showHideConfirm = false"
            class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background text-neutral-black rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
          >
            Keep it
          </button>
          <button
            @click="executeHide"
            class="px-4 py-2 bg-primary text-white hover:bg-primary/90 rounded-xl text-[10px] font-extrabold uppercase tracking-widest transition-all cursor-pointer"
          >
            Remove from list
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
