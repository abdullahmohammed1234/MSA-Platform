<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { ticketTypesService } from '@/services/ems/ticketTypesService';
import { operationsService } from '@/services/ems/operationsService';
import { useToastStore } from '@/components/feedback/toast';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import type { TicketType } from '@/types/ems/ticketing';

const props = defineProps<{
  eventUuid: string;
}>();

const emit = defineEmits<{
  done: [];
}>();

const toast = useToastStore();
const { handle } = useEmsApiError();

const ticketTypes = ref<TicketType[]>([]);
const name = ref('');
const email = ref('');
const phone = ref('');
const ticketTypeId = ref('');
const autoCheckIn = ref(true);
const isSubmitting = ref(false);

const ticketOptions = computed(() =>
  ticketTypes.value.map((type) => ({
    value: type.uuid,
    label: `${type.name} ${Number(type.price) > 0 ? `($${Number(type.price).toFixed(2)})` : '(Free)'}`,
  }))
);

onMounted(async () => {
  try {
    ticketTypes.value = await ticketTypesService.list(props.eventUuid);
    ticketTypeId.value = ticketTypes.value[0]?.uuid ?? '';
  } catch (error) {
    handle(error);
  }
});

const submit = async () => {
  if (!name.value.trim() || !ticketTypeId.value) return;
  isSubmitting.value = true;
  try {
    const result = await operationsService.walkIn(props.eventUuid, {
      attendee_name: name.value.trim(),
      attendee_email: email.value.trim() || undefined,
      attendee_phone: phone.value.trim() || undefined,
      ticket_type_id: ticketTypeId.value,
      check_in: autoCheckIn.value,
    });
    if (result.checkout_url) {
      toast.success('Walk-in created — complete payment to issue the ticket.');
      window.open(result.checkout_url, '_blank');
    } else {
      toast.success(autoCheckIn.value ? 'Walk-in registered and checked in.' : 'Walk-in registered.');
    }
    name.value = '';
    email.value = '';
    phone.value = '';
    emit('done');
  } catch (error) {
    handle(error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <form class="space-y-3 rounded-2xl border border-neutral-ivory bg-white p-4" @submit.prevent="submit">
    <h3 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Walk-in</h3>
    <p class="text-xs text-neutral-muted">
      The attendee is the guest named below — not the staff member recording the walk-in.
    </p>
    <Input v-model="name" placeholder="Full name" required />
    <Input v-model="email" type="email" placeholder="Email (optional)" />
    <Input v-model="phone" placeholder="Phone (optional)" />
    <Select
      v-model="ticketTypeId"
      :options="ticketOptions"
      label="Ticket type"
      placeholder="Ticket type"
      required
    />
    <label class="flex items-center gap-2 text-sm text-neutral-black">
      <input v-model="autoCheckIn" type="checkbox" class="rounded border-neutral-ivory" />
      Check in immediately (free tickets)
    </label>
    <Button type="submit" :disabled="isSubmitting || !name.trim() || !ticketTypeId">
      Register walk-in
    </Button>
  </form>
</template>
