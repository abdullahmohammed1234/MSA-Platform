<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string;
    description?: string;
    /** Rendered above the title as a back link. */
    backTo?: string;
    backLabel?: string;
  }>(),
  { backLabel: 'Back' }
);
</script>

<template>
  <header class="mb-6 space-y-3">
    <router-link
      v-if="backTo"
      :to="backTo"
      class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-muted hover:text-primary transition-colors"
    >
      <span aria-hidden="true">&larr;</span> {{ backLabel }}
    </router-link>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-neutral-black truncate">{{ title }}</h1>
        <p v-if="description" class="mt-1 text-sm text-neutral-muted">{{ description }}</p>
      </div>

      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
