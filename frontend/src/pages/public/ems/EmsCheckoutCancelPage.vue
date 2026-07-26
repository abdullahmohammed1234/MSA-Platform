<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { emsPublicEventPath } from '@/constants/ems';

const route = useRoute();
const slug = computed(() => String(route.params.slug || ''));
const eventPath = computed(() => emsPublicEventPath(slug.value));
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex items-center justify-center px-4 py-24">
    <div class="max-w-lg w-full rounded-[2rem] border border-neutral-ivory bg-white p-8 text-center shadow-sm">
      <h1 class="font-display text-3xl font-bold">Checkout cancelled</h1>
      <p class="mt-3 text-sm text-neutral-black/60 leading-relaxed">
        No payment was taken. You can return to the event page and try again whenever you're ready.
      </p>
      <RouterLink
        :to="eventPath"
        class="mt-8 inline-flex rounded-2xl bg-primary px-5 py-3 text-xs font-extrabold uppercase tracking-widest text-white"
      >
        Return to event
      </RouterLink>
    </div>
  </div>
</template>
