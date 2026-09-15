<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="pb-4 border-b border-neutral-ivory/60">
      <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Unified Release Timeline & Incident Correlation</h3>
      <p class="text-xs text-neutral-muted">
        Chronological event stream linked to release state. Note: Temporal correlation does not prove causation.
      </p>
    </div>

    <!-- Correlation Semantics Legend -->
    <div class="flex flex-wrap items-center gap-3 my-4 p-3 bg-neutral-background/60 border border-neutral-ivory rounded-xl text-xs">
      <span class="font-bold text-neutral-muted">Relationship Semantics:</span>
      <span class="px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 font-semibold">directly_related</span>
      <span class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-700 font-semibold">same_change</span>
      <span class="px-2 py-0.5 rounded-md bg-purple-50 border border-purple-200 text-purple-700 font-semibold">same_entity</span>
      <span class="px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-700 font-semibold">temporal_correlation</span>
    </div>

    <!-- Timeline Stream -->
    <div v-if="!timeline || timeline.length === 0" class="py-8 text-center text-xs text-neutral-muted">
      No timeline events recorded for this release.
    </div>

    <div v-else class="space-y-4 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-neutral-ivory">
      <div
        v-for="(event, idx) in timeline"
        :key="idx"
        class="relative flex items-start gap-4 pl-8"
      >
        <span
          class="absolute left-2 top-1.5 w-3 h-3 rounded-full border-2 border-white"
          :class="relationshipDotClass(event.relationship)"
        ></span>

        <div class="bg-neutral-background/40 border border-neutral-ivory rounded-xl p-4 w-full shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-neutral-black">{{ event.title }}</span>
              <span class="text-xs px-2 py-0.5 rounded-md font-mono font-semibold border" :class="relationshipTagClass(event.relationship)">
                {{ event.relationship }}
              </span>
            </div>
            <span class="text-xs font-mono text-neutral-muted">{{ formatDate(event.timestamp) }}</span>
          </div>

          <p class="text-xs text-neutral-muted leading-relaxed">{{ event.description }}</p>

          <div class="mt-2 text-xs text-neutral-muted font-mono">
            Actor: <span class="text-neutral-black font-medium">{{ event.actor }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { TimelineEvent } from '../../services/releaseService'

defineProps<{
  timeline: TimelineEvent[]
}>()

const relationshipDotClass = (rel: string) => {
  switch (rel) {
    case 'directly_related':
      return 'bg-emerald-600'
    case 'same_change':
      return 'bg-blue-600'
    case 'same_entity':
      return 'bg-purple-600'
    case 'temporal_correlation':
      return 'bg-amber-600'
    default:
      return 'bg-neutral-400'
  }
}

const relationshipTagClass = (rel: string) => {
  switch (rel) {
    case 'directly_related':
      return 'bg-emerald-50 border-emerald-200 text-emerald-700'
    case 'same_change':
      return 'bg-blue-50 border-blue-200 text-blue-700'
    case 'same_entity':
      return 'bg-purple-50 border-purple-200 text-purple-700'
    case 'temporal_correlation':
      return 'bg-amber-50 border-amber-200 text-amber-700'
    default:
      return 'bg-neutral-100 border-neutral-200 text-neutral-700'
  }
}

const formatDate = (ts: string) => {
  return new Date(ts).toLocaleString()
}
</script>
