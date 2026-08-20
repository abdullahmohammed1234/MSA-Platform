<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ArrowLeft,
  Calendar,
  CheckCircle2,
  Clock,
  MapPin,
  Users,
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { useToastStore } from '@/components/feedback/toast';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import pendingCheckoutStorage from '@/services/ems/pendingCheckoutStorage';
import { EMS_PUBLIC_EVENTS_PATH } from '@/constants/ems';
import { resolvePublicImagePath } from '@/constants/publicAssets';
import { useAuthStore } from '@/stores/auth';
import type { PublicEventDetail, PublicRegistration } from '@/types/ems/public';
import type { CheckoutResult, PublicTicketType, WaitlistEntry } from '@/types/ems/ticketing';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();
const { formatDate, formatTime, categorySolidStyle } = useEventFormatting();

const slug = computed(() => String(route.params.slug || ''));
const event = ref<PublicEventDetail | null>(null);
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});
const success = ref<PublicRegistration | null>(null);
const waitlistSuccess = ref<WaitlistEntry | null>(null);
const selectedTicketId = ref<string>('');
const pendingCheckout = ref<CheckoutResult | null>(null);
const completeLater = ref(false);
const lookupEmail = ref('');
const lookingUp = ref(false);

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  student_id: '',
  notes: '',
  quantity: 1,
});

const promoCodeInput = ref('');
const appliedPromoCode = ref<any | null>(null);
const validatingPromo = ref(false);
const promoMessage = ref('');
const promoIsValid = ref(false);

async function applyPromoCode() {
  if (!event.value || !promoCodeInput.value) return;

  validatingPromo.value = true;
  promoMessage.value = '';
  promoIsValid.value = false;

  try {
    const originalTotal = selectedTicket.value ? selectedTicket.value.price * form.quantity : 0;
    const res = await publicEventsService.validatePromoCode({
      code: promoCodeInput.value.trim(),
      event_uuid: event.value.uuid,
      ticket_type_uuid: selectedTicket.value?.uuid || null,
      email: form.email.trim() || null,
      amount: originalTotal,
    });

    if (res.valid) {
      appliedPromoCode.value = res;
      promoIsValid.value = true;
      promoMessage.value = `Promo code applied! Saved ${formatMoney(res.discount_amount, selectedTicket.value?.currency || 'CAD')}`;
    } else {
      appliedPromoCode.value = null;
      promoIsValid.value = false;
      promoMessage.value = 'Invalid promo code.';
    }
  } catch (err: any) {
    appliedPromoCode.value = null;
    promoIsValid.value = false;
    promoMessage.value = err?.message || 'Failed to validate promo code.';
  } finally {
    validatingPromo.value = false;
  }
}

const discountAmount = computed(() => {
  if (!appliedPromoCode.value || !selectedTicket.value) return 0;
  const originalTotal = selectedTicket.value.price * form.quantity;
  if (appliedPromoCode.value.discount_type === 'percentage') {
    return Math.round(originalTotal * (appliedPromoCode.value.discount_value / 100) * 100) / 100;
  } else if (appliedPromoCode.value.discount_type === 'fixed') {
    return Math.min(originalTotal, appliedPromoCode.value.discount_value);
  } else if (appliedPromoCode.value.discount_type === 'free') {
    return originalTotal;
  }
  return 0;
});

const discountedTotal = computed(() => {
  if (!selectedTicket.value) return 0;
  return Math.max(0, (selectedTicket.value.price * form.quantity) - discountAmount.value);
});

watch([selectedTicketId, () => form.quantity, () => form.email], () => {
  if (appliedPromoCode.value) {
    promoCodeInput.value = appliedPromoCode.value.code;
    applyPromoCode();
  }
});

useSeo(() => ({
  title: event.value ? `${event.value.name} | SFU MSA Events` : 'Event | SFU MSA',
  description: event.value?.short_description
    || event.value?.description?.slice(0, 160)
    || 'View event details and register with SFU MSA.',
}));

const ticketTypes = computed<PublicTicketType[]>(() => event.value?.ticket_types ?? []);

const selectedTicket = computed(() =>
  ticketTypes.value.find((t) => t.uuid === selectedTicketId.value) ?? null
);

const isSoldOut = computed(() => Boolean(event.value?.is_full || event.value?.is_sold_out));

const canRegister = computed(() =>
  Boolean(event.value?.is_accepting_registrations && !isSoldOut.value)
);

const canJoinWaitlist = computed(() =>
  Boolean(event.value?.is_accepting_registrations && isSoldOut.value && event.value?.waitlist_enabled)
);

const requiresPayment = computed(() => Boolean(selectedTicket.value && discountedTotal.value > 0));

async function load() {
  loading.value = true;
  error.value = '';
  success.value = null;
  waitlistSuccess.value = null;
  pendingCheckout.value = null;
  completeLater.value = false;

  try {
    event.value = await publicEventsService.getEvent(slug.value);
    const available = (event.value.ticket_types ?? []).filter((t) => t.is_on_sale && !t.is_sold_out);
    selectedTicketId.value = available[0]?.uuid || event.value.ticket_types?.[0]?.uuid || '';
    await restorePending();
  } catch (err) {
    event.value = null;
    error.value = err instanceof EmsApiError ? err.message : 'Event not found.';
  } finally {
    loading.value = false;
  }
}

watch(slug, () => void load(), { immediate: false });
onMounted(() => void load());

function formatMoney(amount: number, currency: string) {
  if (amount === 0) return 'Free';
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount);
}

function persistPending(result: CheckoutResult) {
  if (!event.value || !result.requires_payment || !result.order?.uuid) return;

  pendingCheckoutStorage.save(
    pendingCheckoutStorage.fromCheckoutResult(
      slug.value,
      event.value.name,
      form,
      selectedTicketId.value,
      selectedTicket.value?.name ?? result.registration?.ticket_type?.name ?? '',
      appliedPromoCode.value?.code ?? null,
      result
    )
  );
}

function applyCheckoutToForm(
  result: CheckoutResult,
  hints?: {
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    quantity?: number;
    ticket_type_id?: string;
  } | null
) {
  pendingCheckout.value = result;
  completeLater.value = Boolean(result.requires_payment);

  const reg = result.registration;
  const nameParts = (reg?.attendee_name ?? '').trim().split(/\s+/).filter(Boolean);
  form.first_name = hints?.first_name || form.first_name || nameParts[0] || '';
  form.last_name = hints?.last_name || form.last_name || nameParts.slice(1).join(' ') || '';
  form.email = hints?.email || reg?.attendee_email || form.email;
  form.phone = hints?.phone || form.phone;
  form.quantity = hints?.quantity || reg?.quantity || form.quantity;
  selectedTicketId.value = hints?.ticket_type_id || reg?.ticket_type?.uuid || selectedTicketId.value;
  persistPending(result);
}

async function restorePending() {
  const stored = pendingCheckoutStorage.get(slug.value);
  if (stored) {
    form.first_name = stored.first_name;
    form.last_name = stored.last_name;
    form.email = stored.email;
    form.phone = stored.phone;
    form.quantity = stored.quantity || 1;
    selectedTicketId.value = stored.ticket_type_id || selectedTicketId.value;
    lookupEmail.value = stored.email;
    pendingCheckout.value = pendingCheckoutStorage.toCheckoutResult(stored);
    completeLater.value = true;

    try {
      const fresh = await publicEventsService.resumeCheckout(slug.value, {
        email: stored.email,
        order_uuid: stored.order_uuid,
      });
      applyCheckoutToForm(fresh, stored);
    } catch {
      pendingCheckoutStorage.remove(slug.value);
      pendingCheckout.value = null;
      completeLater.value = false;
      toast.info('Your saved checkout expired. You can start a new one with the details below.');
    }

    if (stored.promo_code) {
      promoCodeInput.value = stored.promo_code;
      await applyPromoCode();
    }
    return;
  }

  if (String(route.query.resume || '') !== '1') return;

  const email = auth.user?.email || String(route.query.email || '').trim();
  if (!email) return;

  lookupEmail.value = email;
  try {
    const fresh = await publicEventsService.resumeCheckout(slug.value, { email });
    applyCheckoutToForm(fresh, { email });
  } catch {
    toast.info('No saved payment was found for this event.');
  }
}

async function findSavedPayment() {
  const email = lookupEmail.value.trim() || form.email.trim();
  if (!email) {
    toast.error('Enter the email you used when you started checkout.');
    return;
  }

  lookingUp.value = true;
  try {
    const fresh = await publicEventsService.resumeCheckout(slug.value, { email });
    form.email = email;
    applyCheckoutToForm(fresh);
    toast.success('Found your saved payment. Complete it when you are ready.');
  } catch (err) {
    toast.error(err instanceof EmsApiError ? err.message : 'No saved payment found for that email.');
  } finally {
    lookingUp.value = false;
  }
}

function paySavedCheckout() {
  const url = pendingCheckout.value?.checkout_url || pendingCheckout.value?.payment?.checkout_url;
  if (!url) {
    void submit(true);
    return;
  }
  window.location.href = url;
}

async function submit(payNow = true) {
  if (!event.value) return;

  submitting.value = true;
  formErrors.value = {};

  try {
    if (canJoinWaitlist.value) {
      waitlistSuccess.value = await publicEventsService.joinWaitlist(slug.value, {
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        quantity: form.quantity,
        ticket_type_id: selectedTicketId.value || undefined,
      });
      toast.success(`You're on the waitlist (position ${waitlistSuccess.value.position}).`);
      return;
    }

    if (!canRegister.value) return;

    const payload = {
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      email: form.email.trim(),
      phone: form.phone.trim() || null,
      student_id: form.student_id.trim() || null,
      notes: form.notes.trim() || null,
      quantity: form.quantity,
      ticket_type_id: selectedTicketId.value || null,
      promo_code: appliedPromoCode.value ? appliedPromoCode.value.code : null,
    };

    if (selectedTicket.value) {
      const result = await publicEventsService.checkout(slug.value, {
        ...payload,
        ticket_type_id: selectedTicket.value.uuid,
        order_uuid: pendingCheckout.value?.order?.uuid || undefined,
      });

      pendingCheckout.value = result;
      persistPending(result);

      if (result.requires_payment && result.checkout_url) {
        if (payNow) {
          toast.success('Redirecting to secure Square checkout…');
          window.location.href = result.checkout_url;
          return;
        }
        completeLater.value = true;
        toast.success('Payment saved. Return to this event page anytime — your details stay on this device.');
        return;
      }

      pendingCheckoutStorage.remove(slug.value);
      success.value = result.registration;
      toast.success('You are registered. Your ticket is ready.');
      const ticketCode = result.registration.tickets?.[0]?.code;
      if (ticketCode) {
        await router.push({ name: 'ems-public-ticket', params: { code: ticketCode } });
      }
      return;
    }

    const registration = await publicEventsService.register(slug.value, payload);
    pendingCheckoutStorage.remove(slug.value);
    success.value = registration;
    toast.success('You are registered. Your ticket is ready.');
    const ticketCode = registration.tickets?.[0]?.code;
    if (ticketCode) {
      await router.push({ name: 'ems-public-ticket', params: { code: ticketCode } });
    }
  } catch (err) {
    if (err instanceof EmsApiError) {
      formErrors.value = err.errors;
      toast.error(err.message);
    } else {
      toast.error('Registration failed. Please try again.');
    }
  } finally {
    submitting.value = false;
  }
}

async function cancelPending() {
  if (!event.value || !pendingCheckout.value) return;
  submitting.value = true;
  try {
    await publicEventsService.cancelCheckout(
      slug.value,
      form.email.trim(),
      pendingCheckout.value.order.uuid
    );
    pendingCheckout.value = null;
    completeLater.value = false;
    pendingCheckoutStorage.remove(slug.value);
    toast.success('Pending checkout cancelled.');
  } catch (err) {
    toast.error(err instanceof EmsApiError ? err.message : 'Could not cancel checkout.');
  } finally {
    submitting.value = false;
  }
}

function fieldError(field: string): string | undefined {
  return formErrors.value[field]?.[0];
}

function capacityText(): string {
  if (!event.value) return '';
  if (event.value.capacity === null) return 'Open seating';
  if (isSoldOut.value) return event.value.waitlist_enabled ? 'Sold out — waitlist open' : 'Sold out';
  return `${event.value.remaining_capacity} of ${event.value.capacity} spaces remaining`;
}

function ctaLabel(): string {
  if (canJoinWaitlist.value) return submitting.value ? 'Joining…' : 'Join waitlist';
  if (requiresPayment.value) {
    const price = selectedTicket.value
      ? formatMoney(discountedTotal.value, selectedTicket.value.currency)
      : '';
    return submitting.value ? 'Starting checkout…' : `Pay ${price} now`;
  }
  return submitting.value ? 'Registering…' : 'Register for free';
}

const previewSubtotal = computed(() =>
  selectedTicket.value ? selectedTicket.value.price * form.quantity : 0
);
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <div v-if="loading" class="container-custom pt-32" aria-busy="true">
      <div class="h-64 rounded-[2rem] bg-white border border-neutral-ivory animate-pulse" />
      <div class="mt-8 h-40 rounded-[2rem] bg-white border border-neutral-ivory animate-pulse" />
    </div>

    <div v-else-if="error" class="container-custom pt-32 text-center">
      <h1 class="font-display text-3xl font-bold">Event not available</h1>
      <p class="mt-2 text-neutral-black/55">{{ error }}</p>
      <RouterLink
        :to="EMS_PUBLIC_EVENTS_PATH"
        class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-primary"
      >
        <ArrowLeft :size="16" /> Back to events
      </RouterLink>
    </div>

    <template v-else-if="event">
      <section class="relative overflow-hidden bg-primary pt-28 pb-16">
        <img
          v-if="event.banner_url"
          :src="resolvePublicImagePath(event.banner_url)"
          :alt="event.name"
          class="absolute inset-0 h-full w-full object-cover opacity-35"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/95 via-primary/70 to-primary" />

        <div class="container-custom relative z-10">
          <RouterLink
            :to="EMS_PUBLIC_EVENTS_PATH"
            class="inline-flex items-center gap-2 text-white/70 text-xs font-bold uppercase tracking-widest hover:text-white mb-6"
          >
            <ArrowLeft :size="14" /> All events
          </RouterLink>

          <ScrollReveal>
            <div class="flex flex-wrap gap-2 mb-4">
              <span
                v-if="event.category"
                class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest"
                :style="categorySolidStyle(event.category.color)"
              >
                {{ event.category.name }}
              </span>
              <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-accent-gold/20 text-accent-gold">
                {{ event.registration_label }}
              </span>
            </div>

            <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-black text-white tracking-tight max-w-4xl">
              {{ event.name }}
            </h1>
            <p v-if="event.short_description" class="mt-4 text-white/75 max-w-2xl text-lg font-light">
              {{ event.short_description }}
            </p>
          </ScrollReveal>
        </div>
      </section>

      <section class="container-custom -mt-8 relative z-10 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
          <div class="rounded-[1.75rem] border border-neutral-ivory bg-white p-6 sm:p-8 shadow-sm">
            <h2 class="font-display text-2xl font-bold mb-5">Event information</h2>
            <div class="grid gap-4 sm:grid-cols-2 text-sm">
              <div class="flex items-start gap-3">
                <Calendar class="text-primary mt-0.5 shrink-0" :size="18" />
                <div>
                  <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Date</div>
                  <div class="font-semibold">{{ formatDate(event.start_at) }}</div>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <Clock class="text-primary mt-0.5 shrink-0" :size="18" />
                <div>
                  <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Time</div>
                  <div class="font-semibold">
                    {{ formatTime(event.start_at) }}
                    <span v-if="event.end_at"> – {{ formatTime(event.end_at) }}</span>
                  </div>
                </div>
              </div>
              <div v-if="event.location" class="flex items-start gap-3">
                <MapPin class="text-primary mt-0.5 shrink-0" :size="18" />
                <div>
                  <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Location</div>
                  <div class="font-semibold">{{ event.location }}</div>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <Users class="text-primary mt-0.5 shrink-0" :size="18" />
                <div>
                  <div class="text-[10px] uppercase tracking-widest text-neutral-black/40 font-bold">Capacity</div>
                  <div class="font-semibold">{{ capacityText() }}</div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="event.description" class="rounded-[1.75rem] border border-neutral-ivory bg-white p-6 sm:p-8 shadow-sm">
            <h2 class="font-display text-2xl font-bold mb-4">About this event</h2>
            <div class="prose prose-sm max-w-none text-neutral-black/75 whitespace-pre-wrap leading-relaxed">
              {{ event.description }}
            </div>
          </div>
        </div>

        <aside class="lg:sticky lg:top-28 h-fit">
          <div class="rounded-[1.75rem] border border-neutral-ivory bg-white p-6 sm:p-8 shadow-sm">
            <h2 class="font-display text-2xl font-bold mb-2">Registration</h2>
            <p class="text-sm text-neutral-black/55 mb-6">{{ event.registration_label }}</p>

            <div
              v-if="waitlistSuccess"
              class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-sm text-emerald-900"
              role="status"
            >
              You're on the waitlist at position {{ waitlistSuccess.position }}.
            </div>

            <div
              v-else-if="!canRegister && !canJoinWaitlist"
              class="rounded-2xl bg-neutral-background border border-neutral-ivory p-4 text-sm text-neutral-black/65"
              role="status"
            >
              <template v-if="isSoldOut">Sold out</template>
              <template v-else-if="event.status === 'completed'">This event has concluded.</template>
              <template v-else-if="event.status === 'live'">This event is live — registration is closed.</template>
              <template v-else-if="event.status === 'registration_closed'">Registration is closed.</template>
              <template v-else>Registration is not open yet.</template>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submit(true)">
              <div v-if="ticketTypes.length" class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-neutral-black/50">Choose ticket</p>
                <label
                  v-for="ticket in ticketTypes"
                  :key="ticket.uuid"
                  class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                  :class="selectedTicketId === ticket.uuid
                    ? 'border-primary bg-primary/5'
                    : 'border-neutral-ivory hover:border-primary/40'"
                >
                  <input
                    v-model="selectedTicketId"
                    type="radio"
                    class="mt-1"
                    :value="ticket.uuid"
                    :disabled="ticket.is_sold_out || !ticket.is_on_sale"
                  />
                  <div class="flex-1">
                    <div class="flex items-center justify-between gap-2">
                      <span class="font-semibold text-sm">{{ ticket.name }}</span>
                      <span class="text-sm font-bold">{{ formatMoney(ticket.price, ticket.currency) }}</span>
                    </div>
                    <p v-if="ticket.description" class="mt-0.5 text-xs text-neutral-black/55">{{ ticket.description }}</p>
                    <p v-if="ticket.is_sold_out" class="mt-1 text-xs font-semibold text-amber-700">Sold out</p>
                    <p v-else-if="!ticket.is_on_sale" class="mt-1 text-xs text-neutral-muted">Not on sale</p>
                    <p v-else-if="ticket.remaining_quantity !== null" class="mt-1 text-xs text-neutral-muted">
                      {{ ticket.remaining_quantity }} remaining
                    </p>
                  </div>
                </label>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                  First name *
                  <input
                    v-model="form.first_name"
                    required
                    autocomplete="given-name"
                    class="mt-1.5 w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                  />
                  <span v-if="fieldError('first_name')" class="mt-1 block text-red-600 font-medium normal-case tracking-normal">
                    {{ fieldError('first_name') }}
                  </span>
                </label>
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                  Last name *
                  <input
                    v-model="form.last_name"
                    required
                    autocomplete="family-name"
                    class="mt-1.5 w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                  />
                </label>
              </div>

              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                Email *
                <input
                  v-model="form.email"
                  type="email"
                  required
                  autocomplete="email"
                  class="mt-1.5 w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                />
              </label>

              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                Phone
                <input
                  v-model="form.phone"
                  type="tel"
                  autocomplete="tel"
                  class="mt-1.5 w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                />
              </label>

              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                Quantity
                <input
                  v-model.number="form.quantity"
                  type="number"
                  min="1"
                  :max="selectedTicket?.max_per_order || event.max_tickets_per_order || 10"
                  class="mt-1.5 w-full rounded-xl border border-neutral-ivory px-3 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                />
              </label>

              <label v-if="selectedTicket && !selectedTicket.is_free" class="block text-xs font-bold uppercase tracking-wider text-neutral-black/50">
                Promo Code
                <div class="flex gap-2 mt-1.5">
                  <input
                    v-model="promoCodeInput"
                    placeholder="Enter promo code"
                    class="flex-1 rounded-xl border border-neutral-ivory px-3 py-2 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary uppercase"
                  />
                  <button
                    type="button"
                    class="px-4 py-2.5 rounded-xl bg-neutral-ivory hover:bg-neutral-ivory/80 text-xs font-extrabold uppercase tracking-widest cursor-pointer border border-transparent disabled:opacity-50"
                    :disabled="validatingPromo || !promoCodeInput.trim()"
                    @click="applyPromoCode"
                  >
                    {{ validatingPromo ? 'Applying...' : 'Apply' }}
                  </button>
                </div>
                <span v-if="promoMessage" class="mt-1 block text-xs font-medium normal-case tracking-normal" :class="promoIsValid ? 'text-emerald-600' : 'text-red-600'">
                  {{ promoMessage }}
                </span>
              </label>

              <div
                v-if="selectedTicket"
                class="rounded-2xl border border-neutral-ivory bg-neutral-background/80 p-4 text-sm space-y-1.5"
              >
                <p class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/45">Order preview</p>
                <div class="flex justify-between gap-3">
                  <span>{{ selectedTicket.name }} × {{ form.quantity }}</span>
                  <span class="font-semibold">{{ formatMoney(previewSubtotal, selectedTicket.currency) }}</span>
                </div>
                <div v-if="discountAmount > 0" class="flex justify-between gap-3 text-emerald-700">
                  <span>Discount{{ appliedPromoCode?.code ? ` (${appliedPromoCode.code})` : '' }}</span>
                  <span>− {{ formatMoney(discountAmount, selectedTicket.currency) }}</span>
                </div>
                <div class="flex justify-between gap-3 font-bold pt-1 border-t border-neutral-ivory">
                  <span>Total</span>
                  <span>{{ formatMoney(discountedTotal, selectedTicket.currency) }}</span>
                </div>
                <p class="text-[11px] text-neutral-black/45">
                  Preview only. Square charges the amount EMS confirms on checkout.
                </p>
              </div>

              <div
                v-if="completeLater && pendingCheckout?.payment"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 space-y-2"
              >
                <p class="font-semibold">Payment saved for later</p>
                <p>
                  Authorized total:
                  {{ formatMoney(pendingCheckout.payment.amount, pendingCheckout.payment.currency) }}
                  <span v-if="pendingCheckout.payment.checkout_version">
                    · checkout v{{ pendingCheckout.payment.checkout_version }}
                  </span>
                </p>
                <p class="text-xs text-amber-900/80">
                  This stays on this device. Come back to this event page, or sign in and open My Tickets.
                  Change the ticket, quantity, promo, or email above, then pay now if those details changed.
                </p>
                <div class="flex flex-wrap gap-3 pt-1">
                  <button
                    type="button"
                    class="text-xs font-bold uppercase tracking-widest text-primary underline"
                    :disabled="submitting"
                    @click="paySavedCheckout"
                  >
                    Pay with Square
                  </button>
                  <button
                    type="button"
                    class="text-xs font-bold uppercase tracking-widest text-amber-900 underline"
                    :disabled="submitting"
                    @click="cancelPending"
                  >
                    Cancel pending checkout
                  </button>
                </div>
              </div>

              <button
                type="submit"
                class="w-full inline-flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-primary text-white text-xs font-extrabold uppercase tracking-widest hover:brightness-110 disabled:opacity-60 cursor-pointer"
                :disabled="submitting || (ticketTypes.length > 0 && !selectedTicketId)"
              >
                <CheckCircle2 v-if="!submitting" :size="16" />
                {{ ctaLabel() }}
              </button>

              <button
                v-if="requiresPayment"
                type="button"
                class="w-full inline-flex items-center justify-center py-3 rounded-2xl border border-neutral-ivory text-xs font-extrabold uppercase tracking-widest text-neutral-black/70 hover:border-primary/40 disabled:opacity-60 cursor-pointer"
                :disabled="submitting || (ticketTypes.length > 0 && !selectedTicketId)"
                @click="submit(false)"
              >
                {{ completeLater ? 'Update saved payment' : (submitting ? 'Saving…' : 'Complete payment later') }}
              </button>

              <div
                v-if="requiresPayment && !completeLater"
                class="rounded-2xl border border-dashed border-neutral-ivory p-4 space-y-2"
              >
                <p class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/45">
                  Already started checkout?
                </p>
                <div class="flex gap-2">
                  <input
                    v-model="lookupEmail"
                    type="email"
                    placeholder="Email used at checkout"
                    class="flex-1 rounded-xl border border-neutral-ivory px-3 py-2 text-sm focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary"
                  />
                  <button
                    type="button"
                    class="px-3 py-2 rounded-xl bg-neutral-ivory hover:bg-neutral-ivory/80 text-[10px] font-extrabold uppercase tracking-widest cursor-pointer disabled:opacity-50"
                    :disabled="lookingUp"
                    @click="findSavedPayment"
                  >
                    {{ lookingUp ? 'Looking…' : 'Find' }}
                  </button>
                </div>
              </div>

              <p v-if="requiresPayment" class="text-[11px] text-center text-neutral-black/45">
                Secure payment is processed by Square. Tickets are issued only after payment is confirmed.
              </p>
            </form>
          </div>
        </aside>
      </section>
    </template>
  </div>
</template>
