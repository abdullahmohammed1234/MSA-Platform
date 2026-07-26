<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import EmsStatusBadge from './EmsStatusBadge.vue';
import EmsConfirmDialog from './EmsConfirmDialog.vue';
import type { Event, EventAvailableTransition } from '@/types/ems';

/**
 * The event's current state and the actions allowed from it.
 *
 * Every button here is rendered from `event.available_transitions`, which the
 * API computes from the state machine and the viewer's permissions. This
 * component contains no copy of the lifecycle rules, and the server validates
 * the transition again when the button is pressed.
 */
const props = defineProps<{
  event: Event;
  isBusy?: boolean;
}>();

const emit = defineEmits<{ transition: [action: EventAvailableTransition['action']] }>();

/** Transitions the state allows and this user may perform. */
const permitted = computed(() => props.event.available_transitions.filter((t) => t.permitted));

/** Allowed by the state but blocked by permissions — shown as a note, not a button. */
const blocked = computed(() => props.event.available_transitions.filter((t) => !t.permitted));

const pending = ref<EventAvailableTransition | null>(null);

const request = (transition: EventAvailableTransition) => {
  // Irreversible steps always confirm; the rest apply immediately.
  if (transition.irreversible) {
    pending.value = transition;
    return;
  }

  emit('transition', transition.action);
};

const confirm = () => {
  if (!pending.value) return;

  const action = pending.value.action;
  pending.value = null;
  emit('transition', action);
};
</script>

<template>
  <section
    class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"
    aria-labelledby="ems-lifecycle-heading"
  >
    <h2 id="ems-lifecycle-heading" class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">
      Lifecycle
    </h2>

    <div class="mt-3 space-y-1">
      <p class="text-xs text-neutral-muted">Status</p>
      <EmsStatusBadge :label="event.status_label" :tone="event.status_tone" />
    </div>

    <div v-if="permitted.length" class="mt-5 flex flex-wrap gap-2">
      <Button
        v-for="transition in permitted"
        :key="transition.action"
        :variant="transition.irreversible ? 'outline' : 'primary'"
        size="sm"
        :disabled="isBusy"
        :title="transition.confirmation"
        @click="request(transition)"
      >
        {{ transition.label }}
      </Button>
    </div>

    <p v-else-if="blocked.length" class="mt-5 text-xs text-neutral-muted">
      The next step for this event is
      <span class="font-semibold text-neutral-black">{{ blocked[0].label }}</span
      >, which you do not have permission to perform.
    </p>

    <p v-else class="mt-5 text-xs text-neutral-muted">
      This event has reached the end of its lifecycle. No further transitions are available.
    </p>

    <EmsConfirmDialog
      :is-open="pending !== null"
      :title="pending?.label ?? ''"
      :message="pending?.confirmation ?? ''"
      :confirm-label="pending?.label ?? 'Confirm'"
      is-destructive
      :is-busy="isBusy"
      @confirm="confirm"
      @cancel="pending = null"
    />
  </section>
</template>
