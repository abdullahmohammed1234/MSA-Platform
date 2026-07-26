<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { CheckCircle2 } from 'lucide-vue-next';
import { emsPublicEventPath } from '@/constants/ems';

const route = useRoute();
const orderUuid = computed(() => String(route.query.order || ''));
const slug = computed(() => String(route.params.slug || ''));
const eventPath = computed(() => emsPublicEventPath(slug.value));
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex items-center justify-center px-4 py-24">
    <div class="max-w-lg w-full rounded-[2rem] border border-neutral-ivory bg-white p-8 text-center shadow-sm">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
        <CheckCircle2 :size="28" />
      </div>
      <h1 class="font-display text-3xl font-bold">Payment received</h1>
      <p class="mt-3 text-sm text-neutral-black/60 leading-relaxed">
        Square has confirmed your payment. Your ticket will appear as soon as the EMS verifies the
        transaction — usually within a few seconds. Check your email for the confirmation once it is issued.
      </p>
      <p v-if="orderUuid" class="mt-4 text-xs font-mono text-neutral-muted">Order {{ orderUuid }}</p>
      <RouterLink
        :to="eventPath"
        class="mt-8 inline-flex text-sm font-bold text-primary"
      >
        Back to event
      </RouterLink>
    </div>
  </div>
</template>
