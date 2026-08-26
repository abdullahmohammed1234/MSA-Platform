<script setup lang="ts">
import type { PlatformService } from '@/types/systems'
import SystemStatusBadge from './SystemStatusBadge.vue'
import SystemLastChecked from './SystemLastChecked.vue'
import { ChevronRight } from 'lucide-vue-next'

defineProps<{
  service: PlatformService
}>()
</script>

<template>
  <article class="rounded-2xl border border-neutral-ivory bg-white p-5 flex flex-col gap-3">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="text-base font-display font-bold text-neutral-black">{{ service.name }}</h3>
        <p class="mt-1 text-sm text-neutral-muted">{{ service.description }}</p>
      </div>
      <SystemStatusBadge :status="service.status" size="sm" />
    </div>

    <p v-if="service.status_reason || service.message" class="text-xs text-neutral-muted">
      {{ service.status_reason || service.message }}
    </p>

    <dl v-if="service.metrics && Object.keys(service.metrics).length" class="grid grid-cols-2 gap-2 text-sm">
      <div v-for="(value, key) in service.metrics" :key="String(key)">
        <dt class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">{{ key }}</dt>
        <dd class="font-semibold text-neutral-black">{{ value ?? '—' }}</dd>
      </div>
    </dl>

    <SystemLastChecked :checked-at="service.last_checked_at" />

    <router-link
      :to="service.detail_path || `/admin/systems/services/${service.id}`"
      class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
    >
      Investigate
      <ChevronRight class="h-4 w-4" />
    </router-link>
  </article>
</template>
