<script setup lang="ts">
import type { EmsCheckInResult } from '@/types/ems/operations';

defineProps<{
  result: EmsCheckInResult | null;
}>();

const toneClass = (code: string) => {
  if (code === 'checked_in' || code === 'valid') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  }
  if (code === 'already_checked_in') {
    return 'border-amber-200 bg-amber-50 text-amber-950';
  }
  return 'border-red-200 bg-red-50 text-red-900';
};
</script>

<template>
  <div
    v-if="result"
    class="rounded-2xl border px-4 py-4"
    :class="toneClass(result.code)"
    role="status"
  >
    <p class="text-lg font-semibold tracking-tight">{{ result.message }}</p>
    <p v-if="result.registration?.attendee_name" class="mt-1 text-sm opacity-90">
      {{ result.registration.attendee_name }}
      <span v-if="result.ticket?.code" class="font-mono"> · {{ result.ticket.code }}</span>
    </p>
    <p v-if="result.previous_check_in?.checked_in_at" class="mt-2 text-sm">
      Previous check-in:
      {{ new Date(result.previous_check_in.checked_in_at).toLocaleString() }}
      <span v-if="result.previous_check_in.staff_name">
        by {{ result.previous_check_in.staff_name }}
      </span>
    </p>
  </div>
</template>
