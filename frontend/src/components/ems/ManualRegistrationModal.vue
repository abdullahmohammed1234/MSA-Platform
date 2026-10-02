<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog } from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';
import { operationsService } from '@/services/ems/operationsService';
import { emsHttp } from '@/services/ems/emsClient';
import type { TicketType } from '@/types/ems';

const props = defineProps<{
  isOpen: boolean;
  eventUuid: string;
  ticketTypes?: TicketType[];
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'registered'): void;
}>();

const toast = useToastStore();
const isLoading = ref(false);
const fetchedTicketTypes = ref<TicketType[]>([]);
const isFetchingTickets = ref(false);

const form = ref({
  registration_type: 'cash' as 'cash' | 'guest_invite',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  ticket_type_id: '',
  quantity: 1,
  amount: null as number | null,
  notes: '',
});

const availableTicketTypes = computed(() => {
  return props.ticketTypes && props.ticketTypes.length > 0
    ? props.ticketTypes
    : fetchedTicketTypes.value;
});

const selectedTicketType = computed(() => {
  return availableTicketTypes.value.find(
    (t: TicketType) => t.uuid === form.value.ticket_type_id || String((t as any).id) === form.value.ticket_type_id
  );
});

const fetchTickets = async () => {
  if (!props.eventUuid || (props.ticketTypes && props.ticketTypes.length > 0)) return;
  isFetchingTickets.value = true;
  try {
    const res = await emsHttp.get<TicketType[]>(`/events/${props.eventUuid}/tickets`);
    fetchedTicketTypes.value = Array.isArray(res) ? res : (res as any)?.data || [];
    if (fetchedTicketTypes.value.length > 0 && !form.value.ticket_type_id) {
      form.value.ticket_type_id = fetchedTicketTypes.value[0].uuid;
      if (form.value.registration_type === 'cash') {
        form.value.amount = fetchedTicketTypes.value[0].price;
      }
    }
  } catch (err) {
    console.error('Failed to fetch ticket types', err);
  } finally {
    isFetchingTickets.value = false;
  }
};

watch(
  () => props.isOpen,
  (newVal) => {
    if (newVal) {
      resetForm();
      fetchTickets();
    }
  }
);

watch(
  () => form.value.registration_type,
  (type) => {
    if (type === 'guest_invite') {
      form.value.amount = 0;
    } else if (selectedTicketType.value) {
      form.value.amount = selectedTicketType.value.price * form.value.quantity;
    }
  }
);

watch(
  () => form.value.ticket_type_id,
  () => {
    if (form.value.registration_type === 'cash' && selectedTicketType.value) {
      form.value.amount = selectedTicketType.value.price * form.value.quantity;
    }
  }
);

watch(
  () => form.value.quantity,
  (qty) => {
    if (form.value.registration_type === 'cash' && selectedTicketType.value) {
      form.value.amount = selectedTicketType.value.price * qty;
    }
  }
);

const resetForm = () => {
  const defaultType = availableTicketTypes.value[0];
  form.value = {
    registration_type: 'cash',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    ticket_type_id: defaultType?.uuid || '',
    quantity: 1,
    amount: defaultType?.price || 0,
    notes: '',
  };
};

const handleSubmit = async () => {
  if (!form.value.first_name || !form.value.last_name) {
    toast.error('First name and last name are required.');
    return;
  }

  isLoading.value = true;
  try {
    await operationsService.manualRegistration(props.eventUuid, {
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      email: form.value.email || undefined,
      phone: form.value.phone || undefined,
      registration_type: form.value.registration_type,
      amount: form.value.registration_type === 'cash' ? (form.value.amount ?? 0) : 0,
      ticket_type_id: form.value.ticket_type_id || undefined,
      quantity: form.value.quantity,
      notes: form.value.notes || undefined,
    });

    const typeLabel = form.value.registration_type === 'cash' ? 'Cash payment' : 'Invited guest';
    const emailNotice = form.value.email
      ? ` Confirmation and ticket PDF sent to ${form.value.email}`
      : ' Registered without email (physical ticket).';
    toast.success(`${typeLabel} registered!${emailNotice}`);
    emit('registered');
    emit('close');
  } catch (err: any) {
    toast.error(err?.message || 'Failed to register attendee.');
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <Dialog :is-open="isOpen" title="Manual Registration & Guest Invitation" size="lg" @close="$emit('close')">
    <div class="space-y-5">
      <p class="text-xs text-neutral-muted">
        Register attendees who paid cash at the door or invite VIP guests/speakers. Confirmed tickets and QR codes will be emailed immediately.
      </p>

      <!-- Registration Type Toggle Cards -->
      <div class="grid grid-cols-2 gap-3">
        <button
          type="button"
          class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all cursor-pointer"
          :class="
            form.registration_type === 'cash'
              ? 'border-brand-burgundy bg-brand-burgundy/5 ring-1 ring-brand-burgundy text-neutral-black'
              : 'border-neutral-ivory hover:border-neutral-muted text-neutral-muted'
          "
          @click="form.registration_type = 'cash'"
        >
          <div class="flex items-center gap-2 font-bold text-sm">
            <span class="text-base">💵</span> Paid with Cash
          </div>
          <span class="text-[11px] mt-1 opacity-85">
            Record cash received at door or offline. Included in cash analytics.
          </span>
        </button>

        <button
          type="button"
          class="flex flex-col items-start p-3.5 rounded-xl border text-left transition-all cursor-pointer"
          :class="
            form.registration_type === 'guest_invite'
              ? 'border-brand-burgundy bg-brand-burgundy/5 ring-1 ring-brand-burgundy text-neutral-black'
              : 'border-neutral-ivory hover:border-neutral-muted text-neutral-muted'
          "
          @click="form.registration_type = 'guest_invite'"
        >
          <div class="flex items-center gap-2 font-bold text-sm">
            <span class="text-base">🎟️</span> Invited Guest
          </div>
          <span class="text-[11px] mt-1 opacity-85">
            VIP speaker, sponsor, or complimentary pass. Tracked separately in analytics.
          </span>
        </button>
      </div>

      <!-- Form Inputs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            First Name <span class="text-brand-burgundy">*</span>
          </label>
          <input
            v-model="form.first_name"
            type="text"
            placeholder="John"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
            required
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Last Name <span class="text-brand-burgundy">*</span>
          </label>
          <input
            v-model="form.last_name"
            type="text"
            placeholder="Doe"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
            required
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Email Address <span class="text-neutral-muted font-normal text-[11px]">(Optional for physical ticket)</span>
          </label>
          <input
            v-model="form.email"
            type="email"
            placeholder="attendee@example.com (optional)"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">Phone Number</label>
          <input
            v-model="form.phone"
            type="tel"
            placeholder="+1 (778) 000-0000"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-if="availableTicketTypes.length > 0">
          <label class="block text-xs font-semibold text-neutral-black mb-1">Ticket Type</label>
          <select
            v-model="form.ticket_type_id"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none bg-white"
          >
            <option v-for="ticket in availableTicketTypes" :key="ticket.uuid" :value="ticket.uuid">
              {{ ticket.name }} ({{ ticket.price === 0 ? 'Free' : `$${ticket.price}` }})
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">Quantity</label>
          <input
            v-model.number="form.quantity"
            type="number"
            min="1"
            max="50"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Amount Paid ($ CAD)
          </label>
          <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0"
            :disabled="form.registration_type === 'guest_invite'"
            class="w-full h-9 rounded-lg border border-neutral-ivory px-3 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none disabled:bg-neutral-ivory/50"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-neutral-black mb-1">Notes / Internal Reason</label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="e.g. VIP Guest Speaker, Cash collected at entrance table"
          class="w-full rounded-lg border border-neutral-ivory p-2.5 text-sm focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy outline-none"
        />
      </div>
    </div>

    <template #footer>
      <Button variant="ghost" :disabled="isLoading" @click="$emit('close')">Cancel</Button>
      <Button variant="primary" :is-loading="isLoading" @click="handleSubmit">
        Confirm & Send Ticket Email
      </Button>
    </template>
  </Dialog>
</template>
