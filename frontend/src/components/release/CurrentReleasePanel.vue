<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-neutral-ivory/60">
      <div>
        <div class="flex items-center gap-3 mb-1">
          <span class="inline-block w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
          <h2 class="text-xl font-display font-semibold text-primary tracking-wide">Active Production Release</h2>
          <span class="px-2.5 py-0.5 text-xs font-mono rounded-md bg-primary/10 text-primary border border-primary/20 font-semibold">
            {{ release.release_identifier || 'v1.30.0-prod' }}
          </span>
        </div>
        <p class="text-sm text-neutral-muted">
          Central Admin release registry state for production environment
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <span
          class="px-3.5 py-1.5 text-xs font-semibold rounded-xl tracking-wider border shadow-sm"
          :class="statusBadgeClass(release.status)"
        >
          {{ release.status || 'SUCCESSFUL' }}
        </span>
        <button
          @click="$emit('verify')"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary hover:bg-primary-hover text-white transition-colors cursor-pointer shadow-soft"
        >
          Run Verification
        </button>
        <button
          @click="$emit('open-approval')"
          v-if="release.status === 'PLANNED'"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition-colors cursor-pointer shadow-soft"
        >
          Approve Release
        </button>
      </div>
    </div>

    <!-- Metadata Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-6">
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Application Version</div>
        <div class="text-lg font-bold text-neutral-black font-mono">{{ release.application_version || '1.30.0' }}</div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Frontend Build Tag</div>
        <div class="text-lg font-bold text-primary font-mono truncate">{{ release.frontend_build || 'build-prod' }}</div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Rollback Readiness</div>
        <div class="text-lg font-bold font-mono" :class="rollbackColorClass">
          {{ release.rollback_readiness || 'ROLLBACK_MANUAL' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Verification Status</div>
        <div class="text-lg font-bold font-mono" :class="verificationColorClass">
          {{ release.post_release_verification_status || 'SUCCESSFUL' }}
        </div>
      </div>
    </div>

    <!-- Additional Details Footer -->
    <div class="flex flex-wrap items-center justify-between text-xs text-neutral-muted pt-4 border-t border-neutral-ivory/60 gap-2">
      <div>
        Environment: <span class="text-neutral-black font-medium">{{ release.environment || 'production' }}</span>
        | Affected Apps: <span class="text-neutral-black font-medium">{{ (release.affected_applications || []).join(', ') || 'platform' }}</span>
      </div>
      <div v-if="release.released_at">
        Deployed At: <span class="text-neutral-black font-mono">{{ formatDate(release.released_at) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformRelease } from '../../services/releaseService'

const props = defineProps<{
  release: Partial<PlatformRelease>
}>()

defineEmits(['verify', 'open-approval'])

const statusBadgeClass = (status?: string) => {
  switch (status) {
    case 'SUCCESSFUL':
      return 'bg-emerald-50 border-emerald-200 text-emerald-700'
    case 'SUCCESSFUL_WITH_WARNINGS':
      return 'bg-amber-50 border-amber-200 text-amber-700'
    case 'FAILED':
      return 'bg-rose-50 border-rose-200 text-rose-700'
    case 'APPROVED':
      return 'bg-blue-50 border-blue-200 text-blue-700'
    case 'PLANNED':
      return 'bg-neutral-100 border-neutral-200 text-neutral-700'
    default:
      return 'bg-primary/10 border-primary/20 text-primary'
  }
}

const rollbackColorClass = computed(() => {
  const st = props.release.rollback_readiness || 'ROLLBACK_MANUAL'
  if (st === 'ROLLBACK_READY') return 'text-emerald-700'
  if (st === 'ROLLBACK_MANUAL') return 'text-amber-700'
  return 'text-rose-700'
})

const verificationColorClass = computed(() => {
  const st = props.release.post_release_verification_status || 'SUCCESSFUL'
  if (st === 'SUCCESSFUL') return 'text-emerald-700'
  if (st === 'SUCCESSFUL_WITH_WARNINGS') return 'text-amber-700'
  return 'text-rose-700'
})

const formatDate = (ts: string) => {
  return new Date(ts).toLocaleString()
}
</script>
