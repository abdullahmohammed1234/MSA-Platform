<script setup lang="ts">
import { Dialog } from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';

/**
 * Confirmation for a destructive or irreversible action.
 *
 * Used for deletes and for lifecycle transitions the backend flags as
 * irreversible, with the confirmation copy the API supplied.
 */
withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    isDestructive?: boolean;
    isBusy?: boolean;
  }>(),
  {
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    isDestructive: false,
    isBusy: false,
  }
);

defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
  <Dialog :is-open="isOpen" :title="title" size="sm" @close="$emit('cancel')">
    <p class="text-sm text-neutral-muted leading-relaxed">{{ message }}</p>

    <template #footer>
      <Button variant="ghost" :disabled="isBusy" @click="$emit('cancel')">
        {{ cancelLabel }}
      </Button>
      <Button
        :variant="isDestructive ? 'destructive' : 'primary'"
        :is-loading="isBusy"
        @click="$emit('confirm')"
      >
        {{ confirmLabel }}
      </Button>
    </template>
  </Dialog>
</template>
