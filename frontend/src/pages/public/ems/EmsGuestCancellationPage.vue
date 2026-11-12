<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
  Calendar,
  Clock,
  MapPin,
  Loader2,
  Ticket,
  ShieldAlert,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  XCircle,
} from 'lucide-vue-next';
import { useSeo } from '@/composables/useSeo';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';
import { EmsApiError } from '@/services/ems/emsClient';
import publicEventsService from '@/services/ems/publicEventsService';
import { EMS_PUBLIC_EVENTS_PATH, emsPublicEventPath } from '@/constants/ems';
import type { GuestCancellationInspection } from '@/types/ems/public';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const toast = useToastStore();
const { formatDateRange, formatTimeRange } = useEventFormatting();

const token = computed(() => (route.params.token as string) || '');

const isLoading = ref(true);
const isCancelling = ref(false);
const inspection = ref<GuestCancellationInspection | null>(null);
const errorMessage = ref('');
const isCancelledSuccess = ref(false);

useSeo(() => ({
  title: 'Cancel Registration | SFU MSA',
  description: 'Manage or cancel your event registration.',
}));

async function load() {
  if (!token.value) {
    isLoading.value = false;
    inspection.value = {
      state: 'invalid',
      message: 'No cancellation token was provided in the link.',
    };
    return;
  }

  isLoading.value = true;
  errorMessage.value = '';

  try {
    inspection.value = await publicEventsService.inspectGuestCancellation(token.value);
  } catch (err) {
    if (err instanceof EmsApiError) {
      errorMessage.value = err.message;
      inspection.value = {
        state: 'invalid',
        message: err.message,
      };
    } else {
      errorMessage.value = 'Failed to verify cancellation link. Please try again.';
      inspection.value = {
        state: 'invalid',
        message: errorMessage.value,
      };
    }
  } finally {
    isLoading.value = false;
  }
}

async function handleConfirmCancel() {
  if (!token.value || isCancelling.value) return;

  isCancelling.value = true;
  try {
    await publicEventsService.confirmGuestCancellation(token.value);
    isCancelledSuccess.value = true;
    toast.success('Your registration has been cancelled successfully.');
  } catch (err) {
    const msg = err instanceof EmsApiError ? err.message : 'Could not process cancellation. Please try again.';
    toast.error(msg);
    errorMessage.value = msg;
  } finally {
    isCancelling.value = false;
  }
}

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24">
    <div class="container-custom pt-24 sm:pt-28">
      <!-- Container -->
      <div class="max-w-2xl mx-auto">
        <!-- Loading State -->
        <div
          v-if="isLoading"
          class="bg-white rounded-3xl border border-neutral-ivory shadow-soft p-12 text-center"
        >
          <Loader2 class="h-10 w-10 text-primary animate-spin mx-auto mb-4" />
          <h2 class="font-display text-xl font-bold text-primary">Verifying Cancellation Request</h2>
          <p class="mt-2 text-sm text-neutral-black/55">Please wait while we secure your booking details…</p>
        </div>

        <!-- Success State (After Clicking Cancel) -->
        <div
          v-else-if="isCancelledSuccess"
          class="bg-white rounded-3xl border border-green-200 shadow-soft p-8 sm:p-12 text-center"
        >
          <div class="h-16 w-16 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-5 border border-green-200">
            <CheckCircle2 class="h-8 w-8 text-green-600" />
          </div>
          <h2 class="font-display text-2xl sm:text-3xl font-black text-primary">
            Registration Cancelled
          </h2>
          <p class="mt-3 text-sm sm:text-base text-neutral-black/65 max-w-md mx-auto leading-relaxed">
            Your booking for
            <span class="font-semibold text-neutral-black">{{ inspection?.registration?.event.name ?? 'this event' }}</span>
            has been successfully cancelled. Your ticket capacity has been returned to the community.
          </p>

          <div class="mt-6 p-4 rounded-2xl bg-neutral-background border border-neutral-ivory text-xs text-neutral-black/60 max-w-sm mx-auto">
            A cancellation confirmation email has been dispatched to your email address.
          </div>

          <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <RouterLink
              :to="EMS_PUBLIC_EVENTS_PATH"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-primary text-white px-7 py-3 rounded-full text-xs font-extrabold uppercase tracking-widest hover:bg-secondary hover:shadow-premium transition-all"
            >
              Browse More Events
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>
        </div>

        <!-- Valid State (Awaiting User Confirmation) -->
        <div
          v-else-if="inspection && inspection.state === 'valid' && inspection.registration"
          class="bg-white rounded-3xl border border-neutral-ivory shadow-soft overflow-hidden"
        >
          <div class="p-6 sm:p-8 border-b border-neutral-ivory bg-primary/[0.02]">
            <div class="flex items-center gap-2 mb-2">
              <span class="px-3 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-amber-50 text-amber-800 border-amber-200">
                Cancellation Request
              </span>
              <span class="text-xs font-mono text-neutral-black/45">
                Ref: {{ inspection.registration.reference }}
              </span>
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-black text-primary leading-tight">
              Confirm Ticket Cancellation
            </h2>
            <p class="mt-2 text-sm text-neutral-black/60">
              Please review your event details below before confirming cancellation.
            </p>
          </div>

          <!-- Event & Registration Card Details -->
          <div class="p-6 sm:p-8 space-y-6">
            <div class="p-5 rounded-2xl bg-neutral-background border border-neutral-ivory space-y-4">
              <h3 class="font-display text-lg font-bold text-primary">
                {{ inspection.registration.event.name }}
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-neutral-black/70">
                <div class="flex items-center gap-2">
                  <Calendar class="text-primary shrink-0" :size="15" />
                  <span>{{ formatDateRange(inspection.registration.event.start_at, inspection.registration.event.end_at) }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <Clock class="text-primary shrink-0" :size="15" />
                  <span>{{ formatTimeRange(inspection.registration.event.start_at, inspection.registration.event.end_at) }}</span>
                </div>
                <div v-if="inspection.registration.event.location" class="flex items-center gap-2 sm:col-span-2">
                  <MapPin class="text-primary shrink-0" :size="15" />
                  <span>{{ inspection.registration.event.location }}</span>
                </div>
              </div>
            </div>

            <!-- Attendee Details -->
            <div class="border-t border-neutral-ivory pt-5 space-y-3">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-neutral-black/45">
                Attendee & Ticket Information
              </h4>
              <div class="grid grid-cols-2 gap-y-2 text-xs">
                <span class="text-neutral-black/55">Attendee:</span>
                <span class="font-semibold text-neutral-black">{{ inspection.registration.attendee_name }}</span>

                <span class="text-neutral-black/55">Email:</span>
                <span class="font-mono text-neutral-black">{{ inspection.registration.attendee_email_masked }}</span>

                <span class="text-neutral-black/55">Ticket Type:</span>
                <span class="font-medium text-neutral-black">
                  {{ inspection.registration.ticket_type_name }} × {{ inspection.registration.quantity }}
                </span>

                <span class="text-neutral-black/55">Type:</span>
                <span class="font-semibold text-green-700">Free Registration</span>
              </div>
            </div>

            <!-- Caution Banner -->
            <div class="flex items-start gap-3 p-4 rounded-2xl bg-amber-50/80 border border-amber-200 text-xs text-amber-900 leading-relaxed">
              <ShieldAlert class="h-5 w-5 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <p class="font-bold">This action cannot be undone.</p>
                <p class="mt-0.5 text-amber-800">
                  Cancelling will immediately revoke your QR ticket and release your reserved spot for other community members.
                </p>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex flex-col sm:flex-row gap-3 items-center justify-between">
              <RouterLink
                v-if="inspection.registration.event.slug"
                :to="emsPublicEventPath(inspection.registration.event.slug)"
                class="w-full sm:w-auto text-center border border-neutral-ivory text-neutral-black/70 hover:text-neutral-black py-3 px-6 rounded-full text-xs font-bold transition-all"
              >
                Keep My Registration
              </RouterLink>

              <button
                type="button"
                :disabled="isCancelling"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white py-3 px-8 rounded-full text-xs font-extrabold uppercase tracking-widest shadow-soft hover:shadow-premium transition-all disabled:opacity-60 cursor-pointer"
                @click="handleConfirmCancel"
              >
                <Loader2 v-if="isCancelling" class="h-4 w-4 animate-spin" />
                <span>{{ isCancelling ? 'Cancelling Registration…' : 'Confirm Cancellation' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Already Cancelled State -->
        <div
          v-else-if="inspection && inspection.state === 'already_cancelled'"
          class="bg-white rounded-3xl border border-neutral-ivory shadow-soft p-8 sm:p-12 text-center"
        >
          <div class="h-16 w-16 bg-neutral-background rounded-2xl flex items-center justify-center mx-auto mb-4 border border-neutral-ivory">
            <Ticket class="h-8 w-8 text-neutral-black/40" />
          </div>
          <span class="px-3 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-neutral-100 text-neutral-700 border-neutral-200">
            Already Cancelled
          </span>
          <h2 class="mt-4 font-display text-2xl font-bold text-primary">
            Registration Has Already Been Cancelled
          </h2>
          <p class="mt-2 text-sm text-neutral-black/60 max-w-md mx-auto leading-relaxed">
            This registration has already been cancelled. The associated ticket code is no longer active for check-in.
          </p>

          <div v-if="inspection.registration" class="mt-6 p-4 rounded-2xl bg-neutral-background border border-neutral-ivory text-xs max-w-sm mx-auto text-left space-y-1">
            <div><span class="text-neutral-black/55">Event:</span> <span class="font-semibold">{{ inspection.registration.event.name }}</span></div>
            <div><span class="text-neutral-black/55">Reference:</span> <span class="font-mono">{{ inspection.registration.reference }}</span></div>
            <div v-if="inspection.registration.cancelled_at"><span class="text-neutral-black/55">Cancelled on:</span> <span>{{ new Date(inspection.registration.cancelled_at).toLocaleDateString() }}</span></div>
          </div>

          <div class="mt-8">
            <RouterLink
              :to="EMS_PUBLIC_EVENTS_PATH"
              class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-widest hover:bg-secondary transition-all"
            >
              Browse Events
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>
        </div>

        <!-- Expired Token State -->
        <div
          v-else-if="inspection && inspection.state === 'expired'"
          class="bg-white rounded-3xl border border-amber-200 shadow-soft p-8 sm:p-12 text-center"
        >
          <div class="h-16 w-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-amber-200">
            <AlertTriangle class="h-8 w-8 text-amber-600" />
          </div>
          <h2 class="font-display text-2xl font-bold text-primary">
            Cancellation Link Expired
          </h2>
          <p class="mt-3 text-sm text-neutral-black/65 max-w-md mx-auto leading-relaxed">
            This cancellation link has expired. Cancellation links remain valid until the event takes place or up to 30 days after registration.
          </p>
          <p class="mt-2 text-xs text-neutral-black/50">
            If you need assistance, please contact the SFU MSA team directly at
            <a href="mailto:events@sfumsa.ca" class="text-primary font-semibold hover:underline">events@sfumsa.ca</a>.
          </p>

          <div class="mt-8">
            <RouterLink
              :to="EMS_PUBLIC_EVENTS_PATH"
              class="inline-flex items-center gap-2 border border-neutral-ivory text-neutral-black px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-widest hover:border-primary/40 transition-all"
            >
              Return to Events
            </RouterLink>
          </div>
        </div>

        <!-- Forbidden State (Paid registration attempted via guest link) -->
        <div
          v-else-if="inspection && inspection.state === 'forbidden'"
          class="bg-white rounded-3xl border border-red-200 shadow-soft p-8 sm:p-12 text-center"
        >
          <div class="h-16 w-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-red-200">
            <ShieldAlert class="h-8 w-8 text-red-600" />
          </div>
          <h2 class="font-display text-2xl font-bold text-primary">
            Paid Registration Cancellation
          </h2>
          <p class="mt-3 text-sm text-neutral-black/65 max-w-md mx-auto leading-relaxed">
            Paid ticket registrations cannot be directly cancelled online.
            To request a cancellation or refund for a paid ticket, please contact our team directly.
          </p>
          <div class="mt-6 p-4 rounded-2xl bg-neutral-background border border-neutral-ivory text-xs max-w-md mx-auto">
            Please email <a href="mailto:events@sfumsa.ca" class="font-bold text-primary hover:underline">events@sfumsa.ca</a> with your registration reference and order details.
          </div>

          <div class="mt-8">
            <RouterLink
              :to="EMS_PUBLIC_EVENTS_PATH"
              class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-widest hover:bg-secondary transition-all"
            >
              Back to Events
            </RouterLink>
          </div>
        </div>

        <!-- Invalid Link State -->
        <div
          v-else
          class="bg-white rounded-3xl border border-red-200 shadow-soft p-8 sm:p-12 text-center"
        >
          <div class="h-16 w-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-red-200">
            <XCircle class="h-8 w-8 text-red-600" />
          </div>
          <h2 class="font-display text-2xl font-bold text-primary">
            Invalid Cancellation Link
          </h2>
          <p class="mt-3 text-sm text-neutral-black/65 max-w-md mx-auto leading-relaxed">
            {{ inspection?.message || errorMessage || 'This cancellation link is invalid or could not be found. Please check the link from your confirmation email.' }}
          </p>
          <div class="mt-8">
            <RouterLink
              :to="EMS_PUBLIC_EVENTS_PATH"
              class="inline-flex items-center gap-2 border border-neutral-ivory text-neutral-black px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-widest hover:border-primary/40 transition-all"
            >
              Browse Events
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

