<script setup lang="ts">
defineProps<{
  checkedAt?: string | null
  label?: string
}>()

const formatChecked = (iso?: string | null) => {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}
</script>

<template>
  <p class="text-xs text-neutral-muted">
    <span class="font-black uppercase tracking-widest text-[10px] text-neutral-muted">{{ label || 'Last checked' }}</span>
    <span class="ml-2 font-medium text-neutral-black">{{ formatChecked(checkedAt) }}</span>
    <span class="ml-1">(server poll, not a live heartbeat)</span>
  </p>
</template>
