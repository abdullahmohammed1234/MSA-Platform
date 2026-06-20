<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';

withDefaults(
  defineProps<{
    title?: string;
    description?: string;
    actionLabel?: string;
  }>(),
  {
    title: 'Nothing here yet',
    description: 'Check back once activity has been recorded.',
  }
);

defineEmits<{ action: [] }>();
</script>

<template>
  <div class="text-center py-16 border border-dashed border-neutral-ivory rounded-3xl bg-white max-w-md mx-auto space-y-4 px-6">
    <Inbox class="h-12 w-12 text-neutral-300 mx-auto" />
    <h4 class="text-sm font-bold text-neutral-black">{{ title }}</h4>
    <p class="text-xs text-neutral-muted max-w-[280px] mx-auto font-light">{{ description }}</p>
    <button
      v-if="actionLabel"
      type="button"
      class="mx-auto h-10 px-4 rounded-xl bg-primary hover:bg-secondary text-xs font-bold text-white cursor-pointer"
      @click="$emit('action')"
    >
      {{ actionLabel }}
    </button>
    <slot />
  </div>
</template>
