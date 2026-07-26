<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

withDefaults(
  defineProps<{
    title?: string;
    message: string;
    retryLabel?: string;
    /** Omit to render the message without a retry affordance. */
    canRetry?: boolean;
  }>(),
  {
    title: 'Something went wrong',
    retryLabel: 'Try again',
    canRetry: true,
  }
);

defineEmits<{ retry: [] }>();
</script>

<template>
  <div
    class="rounded-2xl border border-red-200 bg-red-50/60 p-6 text-center space-y-3"
    role="alert"
  >
    <AlertTriangle class="h-8 w-8 text-secondary mx-auto" aria-hidden="true" />
    <h3 class="text-sm font-bold text-neutral-black">{{ title }}</h3>
    <p class="text-xs text-neutral-muted max-w-md mx-auto">{{ message }}</p>

    <Button v-if="canRetry" variant="outline" size="sm" @click="$emit('retry')">
      {{ retryLabel }}
    </Button>
  </div>
</template>
