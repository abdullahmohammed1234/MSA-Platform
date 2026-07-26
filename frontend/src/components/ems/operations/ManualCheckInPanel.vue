<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { operationsService, checkInErrorPayload } from '@/services/ems/operationsService';
import type { EmsAttendee, EmsCheckInResult } from '@/types/ems/operations';
import { Search } from 'lucide-vue-next';

const props = defineProps<{
  eventUuid: string;
}>();

const emit = defineEmits<{
  result: [result: EmsCheckInResult];
}>();

const query = ref('');
const results = ref<EmsAttendee[]>([]);
const isSearching = ref(false);
const isCheckingIn = ref(false);

const search = async () => {
  if (!query.value.trim()) {
    results.value = [];
    return;
  }
  isSearching.value = true;
  try {
    const page = await operationsService.listAttendees(props.eventUuid, {
      search: query.value.trim(),
      per_page: 10,
    });
    results.value = page.items;
  } finally {
    isSearching.value = false;
  }
};

const checkIn = async (attendee: EmsAttendee) => {
  isCheckingIn.value = true;
  try {
    const result = await operationsService.manualCheckIn(props.eventUuid, {
      registration_uuid: attendee.uuid,
      ticket_code: attendee.ticket_code ?? undefined,
    });
    emit('result', result);
    await search();
  } catch (error) {
    const payload = checkInErrorPayload(error);
    if (payload) emit('result', payload);
    else throw error;
  } finally {
    isCheckingIn.value = false;
  }
};
</script>

<template>
  <div class="space-y-3">
    <form class="flex gap-2" @submit.prevent="search">
      <div class="relative flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-muted" />
        <Input
          v-model="query"
          class="pl-9"
          placeholder="Search name, email, phone, or ticket"
        />
      </div>
      <Button type="submit" variant="outline" :disabled="isSearching">Search</Button>
    </form>

    <ul v-if="results.length" class="divide-y divide-neutral-ivory rounded-2xl border border-neutral-ivory bg-white">
      <li
        v-for="attendee in results"
        :key="attendee.uuid"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
      >
        <div>
          <p class="text-sm font-medium text-neutral-black">{{ attendee.attendee_name }}</p>
          <p class="text-xs text-neutral-muted">
            {{ attendee.attendee_email }}
            <span v-if="attendee.ticket_code" class="font-mono"> · {{ attendee.ticket_code }}</span>
          </p>
          <p class="text-xs text-neutral-muted">{{ attendee.check_in_status_label }}</p>
        </div>
        <Button
          size="sm"
          :disabled="isCheckingIn || attendee.check_in_status === 'checked_in'"
          @click="checkIn(attendee)"
        >
          Check in
        </Button>
      </li>
    </ul>
  </div>
</template>
