<script setup lang="ts">
import type { SystemStatus } from '@/types/systems'
import { CheckCircle2, AlertTriangle, XCircle, HelpCircle } from 'lucide-vue-next'

const props = defineProps<{
  status: SystemStatus
  size?: 'sm' | 'md'
}>()

const label = () => {
  switch (props.status) {
    case 'operational':
      return 'Operational'
    case 'degraded':
      return 'Degraded'
    case 'unavailable':
      return 'Unavailable'
    default:
      return 'Unknown'
  }
}

const tone = () => {
  switch (props.status) {
    case 'operational':
      return 'text-secondary bg-secondary/10 border-secondary/20'
    case 'degraded':
      return 'text-amber-800 bg-amber-50 border-amber-200'
    case 'unavailable':
      return 'text-red-800 bg-red-50 border-red-200'
    default:
      return 'text-neutral-muted bg-neutral-background border-neutral-ivory'
  }
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold"
    :class="[tone(), size === 'sm' ? 'text-[10px]' : 'text-xs']"
    :aria-label="`Status: ${label()}`"
  >
    <CheckCircle2 v-if="status === 'operational'" class="h-3.5 w-3.5" aria-hidden="true" />
    <AlertTriangle v-else-if="status === 'degraded'" class="h-3.5 w-3.5" aria-hidden="true" />
    <XCircle v-else-if="status === 'unavailable'" class="h-3.5 w-3.5" aria-hidden="true" />
    <HelpCircle v-else class="h-3.5 w-3.5" aria-hidden="true" />
    <span>{{ label() }}</span>
  </span>
</template>
