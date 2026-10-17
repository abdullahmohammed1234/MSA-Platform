<template>
  <Modal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :title="isSuccess ? 'Registration Confirmed!' : `RSVP: ${event.name}`"
    :subtitle="isSuccess ? 'Your ticket reservation is secured.' : 'Confirm your attendance for this event.'"
    max-width="md"
    @close="handleClose"
  >
    <!-- Success State -->
    <div v-if="isSuccess && confirmedRegistration" class="space-y-5 text-center py-4">
      <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center mx-auto ring-8 ring-emerald-50/50">
        <CheckCircle2 class="w-8 h-8" />
      </div>

      <div class="space-y-1">
        <h4 class="text-base font-bold text-neutral-800">Alhamdulillah, You're Registered!</h4>
        <p class="text-xs text-neutral-500 max-w-xs mx-auto">
          A confirmation ticket has been generated and saved to your member wallet.
        </p>
      </div>

      <!-- Ticket Summary Card -->
      <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] text-left space-y-2 text-xs">
        <div class="flex justify-between items-center pb-2 border-b border-[#ebe8de]">
          <span class="text-neutral-500">Booking Reference</span>
          <span class="font-mono font-bold text-[#640c0e]">{{ confirmedRegistration.reference }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-500">Attendee</span>
          <span class="font-semibold text-neutral-800">{{ confirmedRegistration.attendee_name }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-500">Status</span>
          <Badge variant="green" size="sm">{{ confirmedRegistration.status_label || 'Confirmed' }}</Badge>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row gap-2 pt-2">
        <router-link to="/app/tickets" class="flex-1">
          <Button variant="primary" size="md" class="w-full">
            <span>View in My Tickets</span>
          </Button>
        </router-link>
        <Button variant="outline" size="md" @click="handleClose">
          Close
        </Button>
      </div>
    </div>

    <!-- Registration Form -->
    <form v-else @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Error Banner -->
      <div v-if="errorMessage" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-800 flex items-start gap-2">
        <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
        <div class="space-y-0.5">
          <span class="font-bold">Registration Failed</span>
          <p>{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Ticket Type Selection (if multiple exist) -->
      <div v-if="availableTicketTypes.length > 1" class="space-y-1.5">
        <label class="block text-xs font-semibold text-neutral-700">Select Ticket Type *</label>
        <select
          v-model="form.ticket_type_id"
          class="w-full rounded-xl border border-[#ebe8de] bg-white px-3.5 py-2.5 text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
          required
        >
          <option
            v-for="ticket in availableTicketTypes"
            :key="ticket.uuid"
            :value="ticket.uuid"
            :disabled="ticket.is_sold_out"
          >
            {{ ticket.name }} — {{ ticket.is_free ? 'Free' : `$${ticket.price}` }}
            {{ ticket.is_sold_out ? '(Sold Out)' : '' }}
          </option>
        </select>
      </div>

      <!-- Name Fields (Grid) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input
          id="first-name"
          v-model="form.first_name"
          label="First Name *"
          placeholder="First Name"
          required
          :error="validationErrors.first_name?.[0]"
        />
        <Input
          id="last-name"
          v-model="form.last_name"
          label="Last Name *"
          placeholder="Last Name"
          required
          :error="validationErrors.last_name?.[0]"
        />
      </div>

      <!-- Email Field -->
      <Input
        id="attendee-email"
        v-model="form.email"
        type="email"
        label="Email Address *"
        placeholder="student@sfu.ca"
        required
        :error="validationErrors.email?.[0]"
      />

      <!-- Student ID & Phone (Optional) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input
          id="student-id"
          v-model="form.student_id"
          label="Student ID (Optional)"
          placeholder="e.g. 301XXXXXX"
          :error="validationErrors.student_id?.[0]"
        />
        <Input
          id="phone"
          v-model="form.phone"
          type="tel"
          label="Phone Number (Optional)"
          placeholder="(604) XXX-XXXX"
          :error="validationErrors.phone?.[0]"
        />
      </div>

      <!-- Additional Notes / Dietary / Accommodations -->
      <div class="space-y-1">
        <label for="rsvp-notes" class="block text-xs font-semibold text-neutral-700">Special Notes / Accessibility (Optional)</label>
        <textarea
          id="rsvp-notes"
          v-model="form.notes"
          rows="2"
          class="w-full rounded-xl border border-[#ebe8de] bg-white p-3 text-xs text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] resize-none"
          placeholder="Any dietary restrictions, accessibility needs, or comments..."
        ></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="pt-3 border-t border-[#ebe8de] flex items-center justify-end gap-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isSubmitting"
          @click="handleClose"
        >
          Cancel
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :is-loading="isSubmitting"
          :disabled="isSubmitting"
        >
          <span>Confirm Free RSVP</span>
          <template #right-icon>
            <Check class="w-3.5 h-3.5" />
          </template>
        </Button>
      </div>
    </form>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useDashboardStore } from '@/stores/dashboard';
import { emsService } from '@/services/ems.service';
import type { PublicEventDetail, Registration, RegisterPayload, TicketType } from '@/types/ems';
import type { ApiError } from '@/types/api';
import Modal from '@/components/ui/Modal.vue';
import Input from '@/components/ui/Input.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import { CheckCircle2, AlertCircle, Check } from 'lucide-vue-next';

interface Props {
  modelValue: boolean;
  event: PublicEventDetail;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'success', registration: Registration): void;
}>();

const authStore = useAuthStore();
const dashboardStore = useDashboardStore();

const isSubmitting = ref<boolean>(false);
const isSuccess = ref<boolean>(false);
const confirmedRegistration = ref<Registration | null>(null);
const errorMessage = ref<string | null>(null);
const validationErrors = ref<Record<string, string[]>>({});

// Compute available ticket types
const availableTicketTypes = computed<TicketType[]>(() => {
  return props.event.ticket_types || [];
});

// Split member name into first & last name
function extractNameParts(fullName: string) {
  const parts = fullName.trim().split(' ');
  if (parts.length === 1) return { first: parts[0], last: '' };
  const first = parts[0];
  const last = parts.slice(1).join(' ');
  return { first, last };
}

interface RsvpFormState {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  student_id: string;
  notes: string;
  ticket_type_id: string;
  quantity: number;
}

const form = ref<RsvpFormState>({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  student_id: '',
  notes: '',
  ticket_type_id: '',
  quantity: 1,
});

// Initialize form from authenticated user profile
function initForm() {
  const user = authStore.user;
  const nameParts = user?.name ? extractNameParts(user.name) : { first: '', last: '' };

  // Select default ticket type if available
  const defaultTicket = availableTicketTypes.value.find((t) => !t.is_sold_out);

  form.value = {
    first_name: nameParts.first,
    last_name: nameParts.last,
    email: user?.email || '',
    phone: '',
    student_id: '',
    notes: '',
    ticket_type_id: defaultTicket?.uuid || '',
    quantity: 1,
  };

  isSuccess.value = false;
  confirmedRegistration.value = null;
  errorMessage.value = null;
  validationErrors.value = {};
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      initForm();
    }
  },
  { immediate: true }
);

async function handleSubmit() {
  if (isSubmitting.value) return;

  isSubmitting.value = true;
  errorMessage.value = null;
  validationErrors.value = {};

  try {
    const payload: RegisterPayload = {
      first_name: form.value.first_name.trim(),
      last_name: form.value.last_name.trim(),
      email: form.value.email.trim(),
      quantity: 1,
    };

    if (form.value.phone.trim()) payload.phone = form.value.phone.trim();
    if (form.value.student_id.trim()) payload.student_id = form.value.student_id.trim();
    if (form.value.notes.trim()) payload.notes = form.value.notes.trim();
    if (form.value.ticket_type_id) payload.ticket_type_id = form.value.ticket_type_id;

    const response = await emsService.registerForEvent(props.event.slug, payload);
    confirmedRegistration.value = response.data;
    isSuccess.value = true;

    // Invalidate dashboard cache so upcoming spotlight refreshes on next visit
    dashboardStore.invalidateCache();

    emit('success', response.data);
  } catch (err: unknown) {
    const apiErr = err as ApiError;
    if (apiErr.errors) {
      validationErrors.value = apiErr.errors;
    }
    errorMessage.value = apiErr.message || 'Registration request could not be processed.';
  } finally {
    isSubmitting.value = false;
  }
}

function handleClose() {
  emit('update:modelValue', false);
}
</script>
