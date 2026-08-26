<script setup lang="ts">
import type { SystemDependencyDetail } from '@/types/systems'
import SystemStatusBadge from './SystemStatusBadge.vue'
import { Check, X, AlertTriangle, HelpCircle } from 'lucide-vue-next'

defineProps<{
  dependencies: SystemDependencyDetail[]
}>()
</script>

<template>
  <ul class="space-y-2">
    <li
      v-for="dep in dependencies"
      :key="dep.id"
      class="flex items-start justify-between gap-3 rounded-xl border border-neutral-ivory px-3 py-2.5"
    >
      <div class="flex items-start gap-2 min-w-0">
        <span class="mt-0.5 shrink-0" aria-hidden="true">
          <Check v-if="dep.status === 'operational'" class="h-4 w-4 text-secondary" />
          <AlertTriangle v-else-if="dep.status === 'degraded'" class="h-4 w-4 text-amber-600" />
          <X v-else-if="dep.status === 'unavailable'" class="h-4 w-4 text-red-600" />
          <HelpCircle v-else class="h-4 w-4 text-neutral-muted" />
        </span>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-neutral-black">{{ dep.label }}</p>
          <p v-if="dep.message" class="text-xs text-neutral-muted mt-0.5">{{ dep.message }}</p>
        </div>
      </div>
      <SystemStatusBadge :status="dep.status" size="sm" />
    </li>
  </ul>
</template>
