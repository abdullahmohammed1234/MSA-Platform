<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-ivory/60">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Post-Release Health Verification</h3>
        <p class="text-xs text-neutral-muted">5-domain health probe evaluation conducted post-deployment</p>
      </div>

      <div class="flex items-center gap-3">
        <span
          class="px-3.5 py-1.5 text-xs font-semibold rounded-xl tracking-wider border shadow-sm"
          :class="verificationBadgeClass(verification.verification_status)"
        >
          {{ verification.verification_status || 'SUCCESSFUL' }}
        </span>
        <button
          @click="$emit('run-verify')"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary hover:bg-primary-hover text-white transition-colors cursor-pointer shadow-soft"
        >
          Re-Run Verification
        </button>
      </div>
    </div>

    <!-- Summary Counts -->
    <div v-if="verification.summary" class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-6">
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-2xl font-bold font-display text-emerald-700">{{ verification.summary.passed }}</div>
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mt-0.5">Passed Probes</div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-2xl font-bold font-display text-amber-700">{{ verification.summary.warning }}</div>
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mt-0.5">Warnings</div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-2xl font-bold font-display text-rose-700">{{ verification.summary.failed }}</div>
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mt-0.5">Failed Probes</div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-2xl font-bold font-display text-neutral-muted">{{ verification.summary.not_verified }}</div>
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mt-0.5">Not Verified</div>
      </div>
    </div>

    <!-- Probes List -->
    <div class="space-y-3">
      <div
        v-for="(probe, key) in verification.probes"
        :key="key"
        class="bg-neutral-background/40 border rounded-xl p-4 transition-colors"
        :class="probeBorderClass(probe.status)"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-mono px-2 py-0.5 rounded-md bg-primary/10 text-primary border border-primary/20 font-semibold">
                {{ key }}
              </span>
              <h4 class="text-sm font-semibold text-neutral-black">{{ probe.name }}</h4>
            </div>
            <p class="text-xs text-neutral-muted leading-relaxed">{{ probe.details }}</p>
          </div>

          <span
            class="px-2.5 py-1 text-xs font-mono font-semibold rounded-md shrink-0 border"
            :class="probeBadgeClass(probe.status)"
          >
            {{ probe.status }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { VerificationResult } from '../../services/releaseService'

defineProps<{
  verification: Partial<VerificationResult>
}>()

defineEmits(['run-verify'])

const verificationBadgeClass = (status?: string) => {
  switch (status) {
    case 'SUCCESSFUL':
      return 'bg-emerald-50 border-emerald-200 text-emerald-700'
    case 'SUCCESSFUL_WITH_WARNINGS':
      return 'bg-amber-50 border-amber-200 text-amber-700'
    case 'FAILED':
      return 'bg-rose-50 border-rose-200 text-rose-700'
    default:
      return 'bg-neutral-100 border-neutral-200 text-neutral-700'
  }
}

const probeBorderClass = (status: string) => {
  switch (status) {
    case 'PASSED':
      return 'border-emerald-200 hover:border-emerald-300'
    case 'WARNING':
      return 'border-amber-200 hover:border-amber-300'
    case 'FAILED':
      return 'border-rose-200 hover:border-rose-300'
    default:
      return 'border-neutral-ivory hover:border-neutral-gray/50'
  }
}

const probeBadgeClass = (status: string) => {
  switch (status) {
    case 'PASSED':
      return 'bg-emerald-50 border-emerald-200 text-emerald-700'
    case 'WARNING':
      return 'bg-amber-50 border-amber-200 text-amber-700'
    case 'FAILED':
      return 'bg-rose-50 border-rose-200 text-rose-700'
    default:
      return 'bg-neutral-100 border-neutral-200 text-neutral-700'
  }
}
</script>
