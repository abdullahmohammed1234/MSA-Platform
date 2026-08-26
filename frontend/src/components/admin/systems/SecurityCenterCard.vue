<script setup lang="ts">
import type { SecurityCenterEntry } from '@/types/systems'
import SystemStatusBadge from './SystemStatusBadge.vue'
import { Shield, ChevronRight } from 'lucide-vue-next'

defineProps<{
  security: SecurityCenterEntry
}>()
</script>

<template>
  <article class="rounded-2xl border border-neutral-ivory bg-white p-5 flex flex-col gap-3">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-start gap-3">
        <div class="rounded-xl bg-primary/10 p-2 text-primary">
          <Shield class="h-5 w-5" />
        </div>
        <div>
          <h3 class="text-base font-display font-bold text-neutral-black">{{ security.name }}</h3>
          <p class="mt-1 text-sm text-neutral-muted">{{ security.description }}</p>
        </div>
      </div>
      <SystemStatusBadge :status="security.status" size="sm" />
    </div>
    <p v-if="security.message" class="text-xs text-neutral-muted">{{ security.message }}</p>
    <router-link
      v-if="security.admin_path"
      :to="security.admin_path"
      class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
    >
      Open Security Center
      <ChevronRight class="h-4 w-4" />
    </router-link>
  </article>
</template>
