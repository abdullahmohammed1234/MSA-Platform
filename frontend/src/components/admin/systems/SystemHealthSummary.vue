<script setup lang="ts">
import type { SystemCheck } from '@/types/systems'
import SystemStatusBadge from './SystemStatusBadge.vue'

defineProps<{
  statusReason?: string | null
  checks?: Record<string, SystemCheck> | Array<{ label: string; status: string; message?: string | null }>
  errors?: Array<{ message: string; severity?: string }>
}>()

const asList = (checks: any): Array<{ label: string; status: string; message?: string | null }> => {
  if (!checks) return []
  if (Array.isArray(checks)) return checks
  return Object.entries(checks).map(([key, value]: [string, any]) => ({
    label: value?.label || key,
    status: value?.status || 'unknown',
    message: value?.message ?? null,
  }))
}
</script>

<template>
  <div class="space-y-3">
    <p v-if="statusReason" class="text-sm text-neutral-black">
      <span class="text-[10px] font-black uppercase tracking-widest text-neutral-muted block mb-1">Why</span>
      {{ statusReason }}
    </p>

    <ul v-if="asList(checks).length" class="space-y-2">
      <li
        v-for="(check, idx) in asList(checks)"
        :key="idx"
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-xl border border-neutral-ivory bg-neutral-background/50 px-3 py-2"
      >
        <div>
          <p class="text-sm font-semibold text-neutral-black">{{ check.label }}</p>
          <p v-if="check.message" class="text-xs text-neutral-muted mt-0.5">{{ check.message }}</p>
        </div>
        <SystemStatusBadge :status="(check.status as any) || 'unknown'" size="sm" />
      </li>
    </ul>

    <div v-if="errors?.length" class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs text-amber-950">
      <p class="font-bold mb-1">Diagnostics</p>
      <ul class="space-y-1">
        <li v-for="(err, idx) in errors.slice(0, 5)" :key="idx">{{ err.message }}</li>
      </ul>
    </div>
  </div>
</template>
