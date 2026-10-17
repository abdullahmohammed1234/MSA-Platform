<template>
  <Modal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Cancel Event Reservation"
    subtitle="Please confirm you wish to cancel this ticket."
    max-width="sm"
    @close="handleClose"
  >
    <div class="space-y-4">
      <!-- Warning Alert -->
      <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start gap-2.5">
        <AlertTriangle class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
        <p class="leading-relaxed">
          Cancelling will permanently release your spot. If the event reaches capacity, you may not be able to re-register.
        </p>
      </div>

      <!-- Registration Summary -->
      <div v-if="registration" class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] text-xs space-y-2">
        <div class="flex justify-between items-center pb-2 border-b border-[#ebe8de]">
          <span class="text-neutral-500">Event</span>
          <span class="font-bold text-[#640c0e] text-right truncate max-w-[200px]">
            {{ registration.event?.name || 'Selected Event' }}
          </span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-500">Reference</span>
          <span class="font-mono font-semibold text-neutral-800">{{ registration.reference }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-500">Attendee</span>
          <span class="font-medium text-neutral-700">{{ registration.attendee_name }}</span>
        </div>
      </div>

      <!-- Error Message if API fails -->
      <div v-if="ticketsStore.cancelError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-800 flex items-start gap-2">
        <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
        <p>{{ ticketsStore.cancelError }}</p>
      </div>

      <!-- Modal Footer -->
      <div class="pt-2 border-t border-[#ebe8de] flex items-center justify-end gap-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="ticketsStore.isCancelling"
          @click="handleClose"
        >
          Keep Reservation
        </Button>
        <Button
          type="button"
          variant="destructive"
          size="sm"
          :is-loading="ticketsStore.isCancelling"
          :disabled="ticketsStore.isCancelling"
          @click="confirmCancel"
        >
          <span>Cancel Ticket</span>
        </Button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { useTicketsStore } from '@/stores/tickets';
import type { Registration } from '@/types/ems';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';
import { AlertTriangle, AlertCircle } from 'lucide-vue-next';

interface Props {
  modelValue: boolean;
  registration: Registration | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'cancelled'): void;
}>();

const ticketsStore = useTicketsStore();

function handleClose() {
  ticketsStore.cancelError = null;
  emit('update:modelValue', false);
}

async function confirmCancel() {
  if (!props.registration) return;

  const success = await ticketsStore.cancelRegistration(props.registration.uuid);
  if (success) {
    emit('cancelled');
    handleClose();
  }
}
</script>
